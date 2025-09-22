<?php
/**
 * Plugin Name: TG Client Portal (Admin v1)
 * Description: Espace admin sécurisé pour gérer Devis & Factures liés à des clients.
 * Version: 0.1.0
 * Author: Thomas
 */
if (!defined('ABSPATH')) exit;

define('TGCP_VER','0.1.0');

/** Helpers: ajouter toutes les caps d'un CPT à un rôle */
function tgcp_grant_caps($role_name, $singular, $plural){
  if (!$role = get_role($role_name)) return;
  $caps = [
    "edit_{$singular}",
    "read_{$singular}",
    "delete_{$singular}",
    "edit_{$plural}",
    "edit_others_{$plural}",
    "publish_{$plural}",
    "read_private_{$plural}",
    "delete_{$plural}",
    "delete_private_{$plural}",
    "delete_published_{$plural}",
    "delete_others_{$plural}",
    "edit_private_{$plural}",
    "edit_published_{$plural}",
  ];
  foreach($caps as $cap){ $role->add_cap($cap); }
}

/** Activation: crée les rôles & caps */
register_activation_hook(__FILE__, function(){
  add_role('client', 'Client', ['read'=>true]);
  add_role('staff', 'Staff', ['read'=>true]);

  // Donner les caps aux rôles pertinents
  foreach (['administrator','staff'] as $r){
    tgcp_grant_caps($r, 'devis', 'devises');
    tgcp_grant_caps($r, 'facture', 'factures');
  }
});

/** Menu parent "Espace client" */
add_action('admin_menu', function(){
  add_menu_page(
    'Espace client',
    'Espace client',
    'edit_devises',          // capability minimale d'accès
    'tgcp',
    function(){
      echo '<div class="wrap"><h1>Espace client</h1><p>Utilise les sous-menus Devis & Factures.</p></div>';
    },
    'dashicons-portfolio',
    25
  );
});

/** CPT Devis & Factures (nids sous le menu tgcp) */
add_action('init', function(){
  register_post_type('devis', [
    'label' => 'Devis',
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => 'tgcp',
    'menu_icon' => 'dashicons-media-spreadsheet',
    'supports' => ['title','editor'],
    'capability_type' => ['devis','devises'],
    'map_meta_cap' => true,
  ]);

  register_post_type('facture', [
    'label' => 'Factures',
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => 'tgcp',
    'menu_icon' => 'dashicons-media-text',
    'supports' => ['title','editor'],
    'capability_type' => ['facture','factures'],
    'map_meta_cap' => true,
  ]);
});

/** Metaboxes (client, montant, statut, échéance, PDF) */
add_action('add_meta_boxes', function(){
  add_meta_box('tgcp_devis_meta','Détails du devis','tgcp_render_devis_meta','devis','normal','default');
  add_meta_box('tgcp_facture_meta','Détails de la facture','tgcp_render_facture_meta','facture','normal','default');
});

function tgcp_render_devis_meta($post){
  wp_nonce_field('tgcp_save_meta','tgcp_nonce');
  $client = (int) get_post_meta($post->ID,'_tgcp_client_user_id',true);
  $montant= get_post_meta($post->ID,'_tgcp_montant_ttc',true);
  $statut = get_post_meta($post->ID,'_tgcp_statut',true);
  $pdf    = get_post_meta($post->ID,'_tgcp_pdf_url',true);

  echo tgcp_users_select('Client','_tgcp_client_user_id',$client);
  echo tgcp_input_money('Montant TTC','_tgcp_montant_ttc',$montant);
  echo tgcp_input_select('Statut','_tgcp_statut',$statut,['brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé']);
  echo tgcp_input_text('Lien PDF (optionnel)','_tgcp_pdf_url',$pdf);
}

function tgcp_render_facture_meta($post){
  wp_nonce_field('tgcp_save_meta','tgcp_nonce');
  $client = (int) get_post_meta($post->ID,'_tgcp_client_user_id',true);
  $montant= get_post_meta($post->ID,'_tgcp_montant_ttc',true);
  $statut = get_post_meta($post->ID,'_tgcp_statut',true);
  $due    = get_post_meta($post->ID,'_tgcp_due_at',true);
  $pdf    = get_post_meta($post->ID,'_tgcp_pdf_url',true);

  echo tgcp_users_select('Client','_tgcp_client_user_id',$client);
  echo tgcp_input_money('Montant TTC','_tgcp_montant_ttc',$montant);
  echo tgcp_input_select('Statut','_tgcp_statut',$statut,['draft'=>'Brouillon','due'=>'À payer','paid'=>'Payée','overdue'=>'En retard']);
  echo tgcp_input_date('Échéance','_tgcp_due_at',$due);
  echo tgcp_input_text('Lien PDF (optionnel)','_tgcp_pdf_url',$pdf);
}

/** Inputs helpers */
function tgcp_users_select($label,$name,$selected){
  $users = get_users(['fields'=>['ID','display_name','user_email'],'orderby'=>'display_name','order'=>'ASC']);
  $html = '<p><label><strong>'.$label.'</strong><br><select name="'.$name.'" style="min-width:300px">';
  $html .= '<option value="">— Sélectionner —</option>';
  foreach($users as $u){
    $sel = selected($selected,(int)$u->ID,false);
    $html .= '<option value="'.(int)$u->ID.'" '.$sel.'>'.esc_html($u->display_name.' ('.$u->user_email.')').'</option>';
  }
  $html .= '</select></label></p>';
  return $html;
}

function tgcp_input_select($label, $name, $current, array $options){
    $html  = '<p><label><strong>'.$label.'</strong><br>';
    $html .= '<select name="'.$name.'" style="min-width:200px">';
    $html .= '<option value="">— Sélectionner —</option>';
    foreach ($options as $val => $text) {
      $val = sanitize_key($val);
      $sel = selected($current, $val, false);
      $html .= '<option value="'.$val.'" '.$sel.'>'.esc_html($text).'</option>';
    }
    $html .= '</select></label></p>';
    return $html;
  }
  


function tgcp_input_text($label,$name,$value){
  return '<p><label><strong>'.$label.'</strong><br><input type="text" name="'.$name.'" value="'.esc_attr($value).'" style="min-width:300px"></label></p>';
}
function tgcp_input_money($label,$name,$value){
  $v = $value!=='' ? esc_attr($value) : '';
  return '<p><label><strong>'.$label.'</strong><br><input type="number" step="0.01" min="0" name="'.$name.'" value="'.$v.'" style="min-width:200px"> €</label></p>';
}
function tgcp_input_date($label,$name,$value){
  return '<p><label><strong>'.$label.'</strong><br><input type="date" name="'.$name.'" value="'.esc_attr($value).'"></label></p>';
}

/** Save metas (sécurisé) */
add_action('save_post', function($post_id){
  if (!isset($_POST['tgcp_nonce']) || !wp_verify_nonce($_POST['tgcp_nonce'],'tgcp_save_meta')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  $post_type = get_post_type($post_id);
  if ($post_type !== 'devis' && $post_type !== 'facture') return;

  if (!current_user_can("edit_{$post_type}", $post_id)) return;

  $map = [
    '_tgcp_client_user_id' => function($v){ return $v ? (int)$v : ''; },
    '_tgcp_montant_ttc'    => function($v){ return $v!=='' ? round((float)$v,2) : ''; },
    '_tgcp_statut'         => function($v){ return sanitize_key($v); },
    '_tgcp_due_at'         => function($v){ return $v ? sanitize_text_field($v) : ''; },
    '_tgcp_pdf_url'        => function($v){ return $v ? esc_url_raw($v) : ''; },
  ];
  foreach($map as $key=>$sanitize){
    if (isset($_POST[$key])) update_post_meta($post_id, $key, $sanitize($_POST[$key]));
  }
});

/** Colonnes admin utiles */
add_filter('manage_devis_posts_columns', function($cols){
  $new = [];
  foreach($cols as $k=>$v){ if ($k==='date') continue; $new[$k]=$v; }
  $new['tgcp_client']  = 'Client';
  $new['tgcp_montant'] = 'Montant';
  $new['tgcp_statut']  = 'Statut';
  $new['date'] = $cols['date'];
  return $new;
});
add_action('manage_devis_posts_custom_column', function($col,$post_id){
  if ($col==='tgcp_client'){
    $uid = (int)get_post_meta($post_id,'_tgcp_client_user_id',true);
    $u = $uid ? get_user_by('id',$uid) : null;
    echo $u ? esc_html($u->display_name) : '—';
  } elseif ($col==='tgcp_montant'){
    $m = get_post_meta($post_id,'_tgcp_montant_ttc',true);
    echo $m!=='' ? esc_html(number_format((float)$m,2,',',' ')).' €' : '—';
  } elseif ($col==='tgcp_statut'){
    echo esc_html(get_post_meta($post_id,'_tgcp_statut',true) ?: '—');
  }
},10,2);

add_filter('manage_facture_posts_columns', function($cols){
  $new = [];
  foreach($cols as $k=>$v){ if ($k==='date') continue; $new[$k]=$v; }
  $new['tgcp_client']  = 'Client';
  $new['tgcp_montant'] = 'Montant';
  $new['tgcp_statut']  = 'Statut';
  $new['tgcp_due']     = 'Échéance';
  $new['date'] = $cols['date'];
  return $new;
});
add_action('manage_facture_posts_custom_column', function($col,$post_id){
  if ($col==='tgcp_client'){
    $uid = (int)get_post_meta($post_id,'_tgcp_client_user_id',true);
    $u = $uid ? get_user_by('id',$uid) : null;
    echo $u ? esc_html($u->display_name) : '—';
  } elseif ($col==='tgcp_montant'){
    $m = get_post_meta($post_id,'_tgcp_montant_ttc',true);
    echo $m!=='' ? esc_html(number_format((float)$m,2,',',' ')).' €' : '—';
  } elseif ($col==='tgcp_statut'){
    echo esc_html(get_post_meta($post_id,'_tgcp_statut',true) ?: '—');
  } elseif ($col==='tgcp_due'){
    $d = get_post_meta($post_id,'_tgcp_due_at',true);
    echo $d ? esc_html(date_i18n(get_option('date_format'), strtotime($d))) : '—';
  }
},10,2);
