<?php
// pi_insert_post
function pi_insert_post()
{
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  $post = new PhraIn\Core\Post();
  $post->fetch($_REQUEST["data"]);
  echo json_encode($post->upsert());
  die;
}
add_action('wp_ajax_nopriv_pi_insert_post', 'pi_insert_post');
add_action('wp_ajax_pi_insert_post', 'pi_insert_post');