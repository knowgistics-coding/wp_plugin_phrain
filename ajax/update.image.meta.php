<?php
function update_image_meta(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  if(isset($_POST["data"][2048])){
    $md5 = md5(file_get_contents($_POST["data"][2048]));
    global $wpdb;
    $query = "SELECT ID FROM {$wpdb->posts} WHERE guid like '%".$md5."%'";
    $attach = $wpdb->get_var($query);
    if(!!$attach){
      if( isset($_POST["data"]["web"]) ){
        update_post_meta($attach,"web",$_POST["data"]["web"]);
      } elseif( isset($_POST["data"]["credit"]) ) {
        update_post_meta($attach,"credit",$_POST["data"]["credit"]);
      }
      echo json_encode(array(
        "status" => true,
        "message" => "Update success.",
      ));
    } else {
      echo json_encode(array(
        "status" => false,
        "message" => "Image not found in Wordpress.",
      ));
    }
  }
  die;
}
add_action('wp_ajax_nopriv_update_image_meta', 'update_image_meta' );
add_action('wp_ajax_update_image_meta', 'update_image_meta' );