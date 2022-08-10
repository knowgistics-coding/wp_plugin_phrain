<?php
function pi_get_category(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  echo json_encode( get_categories(array('hide_empty' => false)) );
  die;
}
add_action('wp_ajax_nopriv_pi_get_category', 'pi_get_category' );
add_action('wp_ajax_pi_get_category', 'pi_get_category' );