<?php
/**
 * Plugin Name: TG Client Portal (Admin v1)
 * Description: Espace admin sécurisé pour gérer Devis & Factures liés à des clients.
 * Version: 0.1.3
 * Author: Thomas
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// Version/paths
define('TGCP_VER', '0.1.2');
define('TGCP_FILE', __FILE__);

// --- Capabilities helper
function tgcp_grant_caps($role_name, $singular, $plural){
  if (!$role = get_role($role_name)) return;
  foreach ([
    "edit_{$singular}","read_{$singular}","delete_{$singular}",
    "edit_{$plural}","edit_others_{$plural}","publish_{$plural}",
    "read_private_{$plural}","delete_{$plural}","delete_private_{$plural}",
    "delete_published_{$plural}","delete_others_{$plural}",
    "edit_private_{$plural}","edit_published_{$plural}"
  ] as $cap) { $role->add_cap($cap); }
}

// --- CPT registration
function tgcp_register_cpts(){
  register_post_type('devis', [
    'label' => 'Devis','public' => false,'show_ui' => true,
    'show_in_menu' => 'tgcp','menu_icon' => 'dashicons-media-spreadsheet',
    'supports' => ['title','editor'],
    'capability_type' => ['devis','devises'],'map_meta_cap' => true,
  ]);
  register_post_type('facture', [
    'label' => 'Factures','public' => false,'show_ui' => true,
    'show_in_menu' => 'tgcp','menu_icon' => 'dashicons-media-text',
    'supports' => ['title','editor'],
    'capability_type' => ['facture','factures'],'map_meta_cap' => true,
  ]);
}
add_action('init', 'tgcp_register_cpts');

// --- Activation/Deactivation
register_activation_hook(__FILE__, function(){
  add_role('client','Client',['read'=>true]);
  add_role('staff','Staff',['read'=>true]);
  foreach (['administrator','staff'] as $r){
    tgcp_grant_caps($r,'devis','devises');
    tgcp_grant_caps($r,'facture','factures');
  }
  tgcp_register_cpts();
  flush_rewrite_rules(false);
});
register_deactivation_hook(__FILE__, function(){
  flush_rewrite_rules(false);
});

// --- Admin menu
add_action('admin_menu', function(){
  add_menu_page('Espace client','Espace client','edit_devises','tgcp', function(){
    echo '<div class="wrap"><h1>Espace client</h1><p>Utilisez les sous-menus Devis & Factures.</p></div>';
  }, 'dashicons-portfolio', 25);
});

// --- Metaboxes
add_action('add_meta_boxes', function(){
  add_meta_box('tgcp_devis_meta','Détails du devis','tgcp_render_devis_meta','devis','normal','default');
  add_meta_box('tgcp_facture_meta','Détails de la facture','tgcp_render_facture_meta','facture','normal','default');
});

function tgcp_users_select($label,$name,$selected){
  $users = get_users(['fields'=>['ID','display_name','user_email'],'orderby'=>'display_name','order'=>'ASC']);
  $h = '<p><label><strong>'.$label.'</strong><br><select name="'.$name.'" style="min-width:320px">';
  $h .= '<option value="">— Sélectionner —</option>';
  foreach($users as $u){
    $sel = selected($selected,(int)$u->ID,false);
    $h .= '<option value="'.(int)$u->ID.'" '.$sel.'>'.esc_html($u->display_name.' ('.$u->user_email.')').'</option>';
  }
  return $h.'</select></label></p>';
}
function tgcp_input_text($label,$name,$value){
  return '<p><label><strong>'.$label.'</strong><br><input type="text" name="'.$name.'" value="'.esc_attr($value).'" style="min-width:320px"></label></p>';
}
function tgcp_input_money($label,$name,$value){
  $v = ($value!=='' && $value!==null) ? esc_attr($value) : '';
  return '<p><label><strong>'.$label.'</strong><br><input type="number" step="0.01" min="0" name="'.$name.'" value="'.$v.'" style="min-width:200px"> €</label></p>';
}
function tgcp_input_date($label,$name,$value){
  return '<p><label><strong>'.$label.'</strong><br><input type="date" name="'.$name.'" value="'.esc_attr($value).'"></label></p>';
}
if (!function_exists('tgcp_input_select')) {
  function tgcp_input_select($label,$name,$current,array $options){
    $h = '<p><label><strong>'.$label.'</strong><br><select name="'.$name.'" style="min-width:200px"><option value="">— Sélectionner —</option>';
    foreach($options as $val=>$text){
      $val = sanitize_key($val);
      $h .= '<option value="'.$val.'" '.selected($current,$val,false).'>'.esc_html($text).'</option>';
    }
    return $h.'</select></label></p>';
  }
}


function tgcp_render_devis_meta($post){
  wp_nonce_field('tgcp_save_meta','tgcp_nonce');
  $client = (int)get_post_meta($post->ID,'_tgcp_client_user_id',true);
  $montant= get_post_meta($post->ID,'_tgcp_montant_ttc',true);
  $statut = get_post_meta($post->ID,'_tgcp_statut',true);
  $pdf    = get_post_meta($post->ID,'_tgcp_pdf_url',true);

  echo tgcp_users_select('Client','_tgcp_client_user_id',$client);
  echo tgcp_input_money('Montant TTC','_tgcp_montant_ttc',$montant);
  echo tgcp_input_select('Statut','_tgcp_statut',$statut,[
    'brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé'
  ]);
  echo tgcp_input_text('Lien PDF (optionnel)','_tgcp_pdf_url',$pdf);
}
function tgcp_render_facture_meta($post){
  wp_nonce_field('tgcp_save_meta','tgcp_nonce');
  $client = (int)get_post_meta($post->ID,'_tgcp_client_user_id',true);
  $montant= get_post_meta($post->ID,'_tgcp_montant_ttc',true);
  $statut = get_post_meta($post->ID,'_tgcp_statut',true);
  $due    = get_post_meta($post->ID,'_tgcp_due_at',true);
  $pdf    = get_post_meta($post->ID,'_tgcp_pdf_url',true);

  echo tgcp_users_select('Client','_tgcp_client_user_id',$client);
  echo tgcp_input_money('Montant TTC','_tgcp_montant_ttc',$montant);
  echo tgcp_input_select('Statut','_tgcp_statut',$statut,[
    'draft'=>'Brouillon','due'=>'À payer','paid'=>'Payée','overdue'=>'En retard'
  ]);
  echo tgcp_input_date('Échéance','_tgcp_due_at',$due);
  echo tgcp_input_text('Lien PDF (optionnel)','_tgcp_pdf_url',$pdf);
}

// Save metas
add_action('save_post', function($post_id){
  if (!isset($_POST['tgcp_nonce']) || !wp_verify_nonce($_POST['tgcp_nonce'],'tgcp_save_meta')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  $pt = get_post_type($post_id);
  if ($pt!=='devis' && $pt!=='facture') return;
  if (!current_user_can("edit_{$pt}", $post_id)) return;

  $map = [
    '_tgcp_client_user_id' => fn($v)=> $v ? (int)$v : '',
    '_tgcp_montant_ttc'    => fn($v)=> ($v!=='' && $v!==null) ? round((float)$v,2) : '',
    '_tgcp_statut'         => fn($v)=> $v ? sanitize_key($v) : '',
    '_tgcp_due_at'         => fn($v)=> $v ? sanitize_text_field($v) : '',
    '_tgcp_pdf_url'        => fn($v)=> $v ? esc_url_raw($v) : '',
  ];
  foreach($map as $k=>$sanitize){
    if (isset($_POST[$k])) update_post_meta($post_id,$k,$sanitize($_POST[$k]));
  }
});

// Admin columns
add_filter('manage_devis_posts_columns', function($cols){
  $n=[]; foreach($cols as $k=>$v){ if($k!=='date') $n[$k]=$v; }
  $n['tgcp_client']='Client'; $n['tgcp_montant']='Montant'; $n['tgcp_statut']='Statut'; $n['date']=$cols['date']; return $n;
});
add_action('manage_devis_posts_custom_column', function($col,$id){
  if ($col==='tgcp_client'){ $u=(int)get_post_meta($id,'_tgcp_client_user_id',true); $user=$u?get_user_by('id',$u):null; echo $user?esc_html($user->display_name):'—'; }
  if ($col==='tgcp_montant'){ $m=get_post_meta($id,'_tgcp_montant_ttc',true); echo ($m!==''&&$m!==null)?esc_html(number_format((float)$m,2,',',' ')).' €':'—'; }
  if ($col==='tgcp_statut'){ echo esc_html(get_post_meta($id,'_tgcp_statut',true)?:'—'); }
},10,2);

add_filter('manage_facture_posts_columns', function($cols){
  $n=[]; foreach($cols as $k=>$v){ if($k!=='date') $n[$k]=$v; }
  $n['tgcp_client']='Client'; $n['tgcp_montant']='Montant'; $n['tgcp_statut']='Statut'; $n['tgcp_due']='Échéance'; $n['date']=$cols['date']; return $n;
});
add_action('manage_facture_posts_custom_column', function($col,$id){
  if ($col==='tgcp_client'){ $u=(int)get_post_meta($id,'_tgcp_client_user_id',true); $user=$u?get_user_by('id',$u):null; echo $user?esc_html($user->display_name):'—'; }
  if ($col==='tgcp_montant'){ $m=get_post_meta($id,'_tgcp_montant_ttc',true); echo ($m!==''&&$m!==null)?esc_html(number_format((float)$m,2,',',' ')).' €':'—'; }
  if ($col==='tgcp_statut'){ echo esc_html(get_post_meta($id,'_tgcp_statut',true)?:'—'); }
  if ($col==='tgcp_due'){ $d=get_post_meta($id,'_tgcp_due_at',true); echo $d?esc_html(date_i18n(get_option('date_format'), strtotime($d))):'—'; }
},10,2);

// -- Auto-update via GitHub (Plugin Update Checker) --
// On charge TARD et en vérifiant l'existence du fichier.
add_action('plugins_loaded', function () {
    $puc_bootstrap = plugin_dir_path(__FILE__) . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
    if (!is_readable($puc_bootstrap)) return;
    require_once $puc_bootstrap;
  
    if (!class_exists('Puc_v5_Factory')) return;
  
    $updater = Puc_v5_Factory::buildUpdateChecker(
      'https://github.com/Lord-Thomas/tg-client-portal/',
      __FILE__,
      'tg-client-portal'
    );
  
    if (method_exists($updater, 'setBranch')) $updater->setBranch('main');
    if (method_exists($updater, 'setDebugMode')) $updater->setDebugMode(true);
  
    if (defined('TGCP_GITHUB_TOKEN') && TGCP_GITHUB_TOKEN) {
      $updater->setAuthentication(TGCP_GITHUB_TOKEN);
    }
  });

  // Debug updates (temporaire)
  add_action('admin_init', function () {
    if (!current_user_can('update_plugins')) return;
    delete_site_transient('update_plugins');
    if (function_exists('puc_get_updater')) {
        $updater = puc_get_updater('tg-client-portal');
        if ($updater) {
            if (method_exists($updater, 'setDebugMode')) $updater->setDebugMode(true);
            $update = $updater->checkForUpdates();
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[TGCP] PUC update object: ' . print_r($update, true));
            }
        }
    }
});

// ===== DIAGNOSTICS PUC (temporaire) =====
add_action('admin_menu', function () {
  add_submenu_page(
    'tgcp',
    'Diagnostics Mises à jour',
    'Diagnostics',
    'update_plugins',
    'tgcp-diagnostics',
    function () {
      echo '<div class="wrap"><h1>Diagnostics PUC</h1>';

      // 1) Infos locales
      $plugin_file = __FILE__;
      $plugin_data = get_file_data($plugin_file, [
        'Version' => 'Version',
        'Name'    => 'Plugin Name'
      ], 'plugin');
      echo '<h2>Version locale</h2><pre>';
      echo esc_html($plugin_data['Name'] . ' — ' . $plugin_data['Version']);
      echo "</pre>";

      // 2) Test HTTP simple vers GitHub (vérifie que le serveur peut sortir)
      $http_test = wp_remote_get('https://api.github.com/repos/Lord-Thomas/tg-client-portal/commits/main', [
        'timeout' => 10,
        'headers' => ['User-Agent' => 'WordPress; tg-client-portal'],
      ]);
      echo '<h2>Test HTTP → GitHub API</h2><pre>';
      if (is_wp_error($http_test)) {
        echo esc_html('ERREUR HTTP : ' . $http_test->get_error_message());
      } else {
        echo esc_html('HTTP ' . wp_remote_retrieve_response_code($http_test) . ' OK');
      }
      echo "</pre>";

      // 3) Forcer PUC et afficher ce qu'il voit
      // Chemin attendu de PUC :
      $puc_bootstrap = plugin_dir_path(__FILE__) . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
      echo '<h2>PUC</h2><pre>';
      if (!is_readable($puc_bootstrap)) {
        echo esc_html('PUC non trouvé : ' . $puc_bootstrap) . '</pre></div>';
        return;
      }
      require_once $puc_bootstrap;
      if (!class_exists('Puc_v5_Factory')) {
        echo esc_html("Classe Puc_v5_Factory introuvable après require.") . '</pre></div>';
        return;
      }

      $repoUrl = 'https://github.com/Lord-Thomas/tg-client-portal/';
      $updater = Puc_v5_Factory::buildUpdateChecker($repoUrl, __FILE__, 'tg-client-portal');
      if (method_exists($updater, 'setBranch')) $updater->setBranch('main');
      if (method_exists($updater, 'setDebugMode')) $updater->setDebugMode(true);

      // Purge cache WP
      delete_site_transient('update_plugins');

      // Récup info distante
      $info  = $updater->requestInfo();      // métadonnées (version, download_url…)
      $check = $updater->checkForUpdates();  // proposition d'update si version > locale

      echo esc_html("Repo URL : $repoUrl") . "\n";
      echo esc_html('PUC info.version : ' . (is_object($info) && isset($info->version) ? $info->version : 'N/A')) . "\n";
      echo esc_html('PUC info.download_url : ' . (is_object($info) && isset($info->download_url) ? $info->download_url : 'N/A')) . "\n";
      echo esc_html('PUC check.version : ' . (is_object($check) && isset($check->version) ? $check->version : 'N/A')) . "\n";
      echo esc_html('Branche : main') . "\n";
      echo "</pre>";

      echo '<p>Astuce : si <em>PUC info.version</em> est bien supérieure à la version locale, la mise à jour doit apparaître dans “Tableau de bord → Mises à jour”.</p>';

      echo '</div>';
    }
  );
});


  