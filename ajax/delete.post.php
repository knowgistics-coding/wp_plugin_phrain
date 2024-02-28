<?php
// pi_delete_post
function pi_delete_post(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');

  $id = $_REQUEST["data"];
  $post = new PhraIn\Core\Post( $id );
  echo $post->delete();
  die();
}
add_action('wp_ajax_nopriv_pi_delete_post', 'pi_delete_post' );
add_action('wp_ajax_pi_delete_post', 'pi_delete_post' );