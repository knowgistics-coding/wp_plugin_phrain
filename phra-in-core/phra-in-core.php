<?php

namespace PhraIn\Core;

class Info {
  public static function get(){
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $plugins = file_get_contents(__DIR__."/../plugins.json");
    echo $plugins;
    wp_die();
  }
}
add_action('wp_ajax_nopriv_pi_info', array(__NAMESPACE__."\Info", "get") );
add_action('wp_ajax_pi_info', array(__NAMESPACE__."\Info", "get") );

// SECTION - Post
class Post {
  // ANCHOR - Constructor
  public function __construct($ID = NULL){
    $this->ID = $ID;

    $this->data = NULL;
    $this->meta = array();
    $this->thumbnail = NULL;

    if(!!$ID){
      $this->data = $this->fetch($ID);
    }
  }

  // ANCHOR - resolve
  private function resolve($message = "", $result = false){
    return json_encode(array(
      "result" => $result,
      "message" => $message,
    ));
  }

  // ANCHOR - fetch
  public function fetch($ID){
    if(!!$ID){
      $this->ID = $ID;
      $endpoint = "https://johnjadd-3524a.firebaseio.com/ebook/page/".$ID.".json";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $endpoint);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $output = curl_exec($ch);
      curl_close($ch);

      $data = json_decode($output, true);

      $this->data = array(
        "post_title" => $data["label"],
        "post_content" => $data["content"],
        "post_status" => "publish",
        "post_type" => "post",
        "post_date" => date("Y-m-d H:i:s", floor($data["date"] / 1000)),
        "post_modified" => date("Y-m-d H:i:s", floor($data["edit"] / 1000)),
        "post_category" => $this->category_filter($data["cat"]),
      );

      $this->meta["phrain_post_id"] = $ID;
      if(!!$data["secondaryTitle"]){
        $this->meta["phrain_secondaryTitle"] = $data["secondaryTitle"];
      }
      if(isset($data["attach"]["photo"])){
        $this->meta["gallery"] = json_encode($data["attach"]["photo"], JSON_UNESCAPED_SLASHES);
      }
      if(isset($data["author"])){ $this->meta["phrain_author"] = $data["author"]; }
      if(isset($data["cover"])){ $this->attachment($data["cover"]); }

      return $this;
    } else {
      return NULL;
    }
  }

  // ANCHOR - get
  public function get(){
    $args = array(
      'post_type'   => 'any',
      'meta_query'  => array(
        'relation'  => 'AND',
        array( "key"=>"phrain_post_id", "value"=>$this->ID, "compare"=>"LIKE" ),
      ),
    );
    $the_query = new \WP_Query($args);
    if($the_query->post_count>0){
      return $the_query->post;
    } else {
      return NULL;
    }
  }

  // ACNHOR - upsert
  public function upsert($return_id = false){
    $post = $this->get();
    if(!!$post){
      wp_update_post(array_merge($this->data, array("ID"=>$post->ID)));
      $this->data["ID"] = $post->ID;
    } else {
      $this->data["ID"] = wp_insert_post($this->data);
    }

    foreach($this->meta as $key => $value){
      update_post_meta($this->data["ID"], $key, $value);
    }

    if(!!$this->thumbnail){
      set_post_thumbnail($this->data["ID"], $this->thumbnail);
    } else {
      delete_post_thumbnail($this->data["ID"]);
    }

    return $this->data["ID"] ? ( $return_id ? $this->data["ID"] : get_permalink($this->data["ID"]) ) : NULL;
  }

  // ANCHOR - delete
  public function delete(){
    if(!!$this->ID){
      $post = $this->get();
      if(!!$post){
        $post = $the_query->post;
        return $this->resolve("Delete post '".$post->post_title."'", boolval(wp_delete_post($post->ID)));
      } else {
        return $this->resolve("ไม่มี Post นี้ใน Wordpress");
      }
    } else {
      return $this->resolve("ไม่มี Post นี้ใน Wordpress");
    }
  }

  // ANCHOR - attachment
  public function attachment($url){
    if(!!$url){
      $args = array(
        'post_type'   => 'attachment',
        'post_status' => 'any',
        'meta_query'  => array(
          'relation'  => 'AND',
          array( "key"=>"_wp_attachment_ref", "value"=>$url, "compare"=>"LIKE" ),
        ),
      );
      $the_query = new \WP_Query($args);
      if($the_query->post_count>0){
        $attachment = $the_query->post;
        $this->thumbnail = $attachment->ID;
      } else {
        $content = file_get_contents($url);
        $filename = md5($content).".jpg";
        $upload = wp_upload_bits($filename, NULL, $content);
        $filetype = wp_check_filetype($filename, NULL);
        $attachment = array(
          'post_mime_type' => $filetype['type'],
          'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
          'post_content' => '',
          'post_status' => 'inherit',
        );        
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, "_wp_attachment_ref", $url);
        $this->thumbnail = $attach_id;
      }
    }

  }
  
  // ANCHOR - category_filter
  protected function category_filter($catag){
    $key = md5(get_site_url());
    if ($catag[$key] !== false ? isset($catag[$key]["list"]) : false) {
      $result = array();
      foreach ($catag[$key]["list"] as $k => $v) {
        if (!!$v) {
          $result[] = $k;
        }
      }
      return $result;
    } else {
      return array();
    }
  }
}
// !SECTION