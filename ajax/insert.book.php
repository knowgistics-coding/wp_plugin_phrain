<?php
// pi_insert_book
function pi_insert_book(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  $addBook = new addBook( isset($_POST["data"]) ? $_POST["data"] : $_GET["data"] );
  echo $addBook->wp_post_id;
  die;
}
add_action('wp_ajax_nopriv_pi_insert_book', 'pi_insert_book' );
add_action('wp_ajax_pi_insert_book', 'pi_insert_book' );