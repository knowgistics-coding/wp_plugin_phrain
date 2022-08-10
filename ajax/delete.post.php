<?php
// pi_delete_post
function pi_delete_post(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  $args = array(
    'post_type'   => 'any',
    'meta_query'  => array(
      'relation'  => 'AND',
      array( "key"=>"phrain_post_id", "value"=>"$_POST[data]", "compare"=>"LIKE" ),
    ),
  );
  $the_query = new WP_Query($args);
  if($the_query->post_count>0){
    $post = $the_query->post;
    echo json_encode(array(
      "result" => wp_delete_post($post->ID),
      "message" => "Delete post '".$post->post_title."'",
    ));
  } else { echo json_encode(array("result"=>false,"message"=>"ไม่มี Post นี้ใน Wordpress")); }
  die;
}
add_action('wp_ajax_nopriv_pi_delete_post', 'pi_delete_post' );
add_action('wp_ajax_pi_delete_post', 'pi_delete_post' );