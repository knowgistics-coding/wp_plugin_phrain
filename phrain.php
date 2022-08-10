<?php
/*
Plugin Name: Phra.in
Plugin URI: http://phra.in/
Description: Use for syncronize with Phra.in (2018-10-02)
Version: 1.0.2
Author: Knowgistics
Author URI: https://www.facebook.com/knowgistics/
License: GPLv2 or later
Text Domain: phrain
*/

require dirname(__FILE__).'/plugin-update-checker-master/plugin-update-checker.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://johnjadd-3524a.firebaseio.com/wordpress/plugins.json', //Metadata URL.
	__FILE__, //Full path to the main plugin file.
	'phrain' //Plugin slug. Usually it's the same as the name of the directory.
);

define("PHRAIN_PATH", plugin_dir_path( __FILE__ ));

/*--------------------------------------------------------------
## Ajax
--------------------------------------------------------------*/
if( function_exists("ajax_enqueuescripts")==false ){
  function ajax_enqueuescripts() {
    wp_localize_script( 'ajaxloadpost', 'ajax_postajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
  }
  add_action('wp_enqueue_scripts', ajax_enqueuescripts);
}
include(PHRAIN_PATH."ajax/register.phrain.user.php"); // register_phrain_user
include(PHRAIN_PATH."ajax/get.category.php"); // get_category
include(PHRAIN_PATH."ajax/insert.post.php"); // insert_post
include(PHRAIN_PATH."ajax/delete.post.php"); // delete_post
include(PHRAIN_PATH."ajax/insert.book.php"); // insert_book
include(PHRAIN_PATH."ajax/update.image.meta.php"); // update_image_meta

// POST Func. @ 2019/01/11
function wppi_post(){
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  $addPost = new addPost( isset($_POST["data"]) ? $_POST["data"] : $_GET["data"] );
  echo $addPost->wp_post_id;
  wp_die();
}
add_action('wp_ajax_nopriv_wppi_post', 'wppi_post' );
add_action('wp_ajax_wppi_post', 'wppi_post' );

/*--------------------------------------------------------------
## Admin Menu
--------------------------------------------------------------*/
if(function_exists("phrain_plugin_menu")==false){
  function phrain_plugin_menu(){
    add_menu_page("Phra.In Database","Phra.in","manage_options","phra_in_plugin",'phra_in_main_function',"dashicons-controls-repeat",'100');
    // add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = '' )
    add_submenu_page( "phra_in_plugin", "Phra.in Login", "Login", "manage_options", "phra_in_plugin", "phra_in_main_function" );
    add_submenu_page( "phra_in_plugin", "Phra.in Image Meta", "Image Meta", "manage_options", "phra_in_image_meta", "phra_in_image_meta" );
  }
  add_action( 'admin_menu', 'phrain_plugin_menu' );
  function phra_in_main_function(){ include(__DIR__."/pages/index.php"); }
  function phra_in_image_meta(){ include(__DIR__."/pages/image.meta.php"); }
}

/*--------------------------------------------------------------
## Add Post Type
--------------------------------------------------------------*/
if(function_exists("book_setup_post_type")==false){
  function book_setup_post_type() {
    $args = array(
      'public' => true,
      'label' => __( 'Books', 'textdomain' ),
      'menu_icon' => 'dashicons-book',
      'supports' => array( 'title', 'editor', 'custom-fields', 'thumbnail', 'author' ),
      'taxonomies' => array( 'category', 'post_tag' ),
    );
    register_post_type( 'book', $args );
  }
  add_action( 'init', 'book_setup_post_type' );
}
if(function_exists("photoalbum_setup_post_type")==false){
  function photoalbum_setup_post_type() {
    $args = array(
      'public' => true,
      'label' => __( 'Photo Albums', 'textdomain' ),
      'menu_icon' => 'dashicons-camera',
      'supports' => array( 'title', 'editor', 'custom-fields', 'thumbnail', 'author' ),
      'taxonomies' => array( 'category', 'post_tag' ),
    );
    register_post_type( 'photoalbum', $args );
  }
  add_action( 'init', 'photoalbum_setup_post_type' );
}

require_once("inc/class.pi2wp.php");

/*--------------------------------------------------------------
## Photo Meta
--------------------------------------------------------------*/
if(!function_exists('my_image_attachment_fields_to_edit')){
  function my_image_attachment_fields_to_edit($form_fields, $post) {
      $form_fields["web"] = array(
        "label" => __("Website URL"),
        "input" => "text",
        "value" => get_post_meta($post->ID, "web", true),
        "helps" => "เว็บต้นทาง (ในกรณีที่ไม่ได้ถ่ายภาพเอง)",
      );
      $form_fields["credit"] = array(
        "label" => __("Phtotgrapher Name"),
        "input" => "text",
        "value" => get_post_meta($post->ID, "credit", true),
        "helps" => "ผู้ถ่ายภาพ (ในกรณีที่ถ่ายภาพเอง)",
      );
      return $form_fields;
  }
  add_filter("attachment_fields_to_edit", "my_image_attachment_fields_to_edit", null, 2);
  function my_image_attachment_fields_to_save( $post, $attachment ) {
    if(isset($attachment['web'])){
      update_post_meta($post['ID'],'web',esc_url($attachment['web']));
    } 
    if(isset($attachment['credit'])){
      update_post_meta($post['ID'],'credit',$attachment['credit']);
    }
    return $post;
  }
  add_filter( 'attachment_fields_to_save', 'my_image_attachment_fields_to_save', 10, 2 );
}