<?php
if( function_exists("register_phrain_user")==false ){
  function register_phrain_user(){
    header( 'Content-Type: application/json; charset=utf-8' );
    $users = !!get_option("pi_user") ? json_decode(get_option("pi_user"),true) : array() ;
    $users[$_POST["data"]["ID"]] = array(
      "pi_ID" => $_POST["data"]["ID"],
      "pi_email" => $_POST["data"]["email"],
      "wp_ID" => get_current_user_id(),
    );
    update_option("pi_user",json_encode($users));
    echo json_encode( $users );
    die;
  }
  add_action('wp_ajax_nopriv_register_phrain_user', 'register_phrain_user' );
  add_action('wp_ajax_register_phrain_user', 'register_phrain_user' );

  function unregister_phrain_user(){
    header( 'Content-Type: application/json; charset=utf-8' );
    $users = !!get_option("pi_user") ? json_decode(get_option("pi_user"),true) : array() ;
    if(isset($users[$_POST["data"]])){ unset($users[$_POST["data"]]); }
    update_option("pi_user",json_encode($users));
    echo json_encode( $users );
    die;
  }
  add_action('wp_ajax_nopriv_unregister_phrain_user', 'unregister_phrain_user' );
  add_action('wp_ajax_unregister_phrain_user', 'unregister_phrain_user' );
}