<?php
class pi2wp {
  protected function get_id(){
    $args = array(
      'post_type' => array('post','book','photoalbum'),
      'meta_query' => array(
        'relation' => 'OR',
        array( "key" => "phrain_post_id", "value" => $this->pi_post_id, "compare" => "LIKE" ),
      ),
      'posts_per_page' => 1,
    );
    $the_query = new WP_Query($args);
    if( !!$the_query->post ){ return $the_query->post->ID; } else { return 0; }
  }
  public function post_exists($id){
    $args = array(
      'post_type' => array('post'),
      'meta_query' => array(
        'relation' => 'OR',
        array( "key" => "phrain_post_id", "value" => $id, "compare" => "LIKE" ),
      ),
      'posts_per_page' => 1,
    );
    $the_query = new WP_Query($args);
    if( !!$the_query->post ){ return $the_query->post->ID; } else { return false; }
  }
  protected function get_new_post(){
    // Date
    $date = !!$this->data["edit"] ? $this->data["edit"] : $this->data["date"] ;
    $date = strlen($date)>10 ? ceil($date/1000) : $date;
    // Post
    $post = array(
      "post_date"     => date("Y-m-d H:i:s",$date),
      "post_title"    => $this->data["label"],
      "post_content"  => $this->parse_content_post($this->data["content"]),
      "post_status"   => "publish",
      "post_type"     => "post",
      "post_category" => $this->category_filter(),
    );
    // Author
    $users = !!get_option("pi_user") ? json_decode(get_option("pi_user"),true) : array() ;
    if(isset($users[$this->data["user"]])){
      $post["post_author"] = $users[$this->data["user"]]["wp_ID"];
    }
    // Thumbnail
    $this->attach_id = $this->pi_add_photo();
    return $post;
  }
  protected function get_new_book(){
    // Date
    $date = !!$this->data["edit"] ? $this->data["edit"] : $this->data["date"] ;
    $date = strlen($date)>10 ? ceil($date/1000) : $date;
    // Post
    $post = array(
      "post_date"     => date("Y-m-d H:i:s",$date),
      "post_title"    => $this->data["label"],
      "post_status"   => "publish",
      "post_type"     => "book",
      "post_category" => $this->category_filter(),
    );
    // Author
    $users = !!get_option("pi_user") ? json_decode(get_option("pi_user"),true) : array() ;
    if(isset($users[$this->data["user"]])){
      $post["post_author"] = $users[$this->data["user"]]["wp_ID"];
    }
    // Thumbnail
    $this->attach_id = $this->pi_add_photo();
    return $post;
  }
  protected function parse_content_post($content){
    $new = $content;
    preg_match_all("/<iframe(.*)<\/iframe>/i",$new,$match);
    if(!empty($match) ? isset($match[0]) : false){
      foreach($match[0] as $k=>$v){
        preg_match_all("/src=(.*)\"/i",$v,$src);
        if(isset($src[1])){
          $url = preg_replace("/[\\\\\"]/i","",$src[1][0]);
          $new = str_ireplace($v,"[embed]".$url."[/embed]",$new);
        }
      }
    }
    return $new;
  }
  protected function category_filter(){
    $key = md5(get_site_url());
    if($this->data["cat"][$key]!==false ? isset($this->data["cat"][$key]["list"]) : false){
      $result = array();
      foreach($this->data["cat"][$key]["list"] as $k=>$v){
        if(!!$v){ $result[] = $k; }
      }
      return $result;
    } else { return null; }
  }
  
  // ==================================================
  // Image Processor
  // ==================================================
  protected function pi_add_photo(){
    if( function_exists("wp_generate_attachment_metadata")==false ){ require_once( ABSPATH . 'wp-admin/includes/image.php' ); }
    if(!isset($this->data["cover"])){ return false; }
    $content = file_get_contents($this->data["cover"]);
    $md5 = md5($content);
    
    $attach_id = $this->get_attachment_id($md5);
    if( $attach_id==null ){
      $path = wp_upload_dir()["path"]."/$md5.jpg";
      file_put_contents($path, $content);
      while(!file_exists($path)){
        sleep(100);
      }
      $attachment = array(
        'post_mime_type' => "image/jpeg",
        'post_title' => sanitize_file_name("$md5.jpg"),
        'post_content' => '',
        'post_status' => 'inherit'
      );
      $attach_id = wp_insert_attachment( $attachment, $path );
      $attach_data = wp_generate_attachment_metadata( $attach_id, $path );
      $res1 = wp_update_attachment_metadata( $attach_id, $attach_data );
      return $attach_id;
    } else { return $attach_id; }
  }
  public function get_attachment_id($md5) {
    global $wpdb;
    $query = "SELECT ID FROM {$wpdb->posts} WHERE guid like '%".$md5."%'";
    return $wpdb->get_var($query);
  }
}
class addPost extends pi2wp {
  public function __construct($pi_post_id){
    $this->pi_post_id = $pi_post_id;
    $json = file_get_contents("https://johnjadd-3524a.firebaseio.com/ebook/page/{$pi_post_id}.json");
    if($json!=="null"){
      $this->data = json_decode($json, true);
      $this->wp_post_id = $this->get_id();
      $new_book = $this->get_new_post();
      if($this->wp_post_id!==0){
        $new_book["ID"] = $this->wp_post_id;
        $this->wp_post_id = wp_update_post($new_book);
      } else {
        $this->wp_post_id = wp_insert_post($new_book);
        update_post_meta($this->wp_post_id, "phrain_post_id", $this->pi_post_id);
      }
      update_post_meta($this->wp_post_id, "phrain_author", $this->data["author"]);
      update_post_meta($this->wp_post_id, "phrain_secondaryTitle", $this->data["secondaryTitle"]);
      
      //ANCHOR - SET CATEGORIES
      $this->cats();
      
      // Set Map
      if(isset($this->data["map"])){
        update_post_meta($this->wp_post_id, "map", json_encode($this->data["map"], JSON_UNESCAPED_SLASHES));
      }
      // Set Attach
      if(isset($this->data["attach"])){
        if(isset($this->data["attach"]["photo"])){
          update_post_meta($this->wp_post_id, "gallery", json_encode($this->data["attach"]["photo"], JSON_UNESCAPED_SLASHES));
        }
      }
      // Set Thumbnail
      if(isset($this->attach_id)){ set_post_thumbnail($this->wp_post_id, $this->attach_id); }
    }
  }

  private function cats(){
    if(isset($this->data["cat"])){
      foreach($this->data["cat"] as $key => $cat){
        if(isset($cat["url"]) && str_contains($cat["url"], $_SERVER["SERVER_NAME"]) && is_array($cat["list"])){
          $cats = array_keys($cat["list"]);
          wp_set_post_categories($this->wp_post_id, $cats);
        }
      }
    }
  }
}
class addBook extends pi2wp {
  public function __construct($pi_post_id){
    $this->pi_post_id = $pi_post_id;
    $json = file_get_contents("https://johnjadd-3524a.firebaseio.com/ebook/book/{$pi_post_id}.json");
    if($json!=="null"){
      $this->data = json_decode($json, true);
      $this->wp_post_id = $this->get_id();
      $new_book = $this->get_new_book();
      if($this->wp_post_id!==0){
        $new_book["ID"] = $this->wp_post_id;
        $this->wp_post_id = wp_update_post($new_book);
      } else {
        $this->wp_post_id = wp_insert_post($new_book);
      }
      update_post_meta($this->wp_post_id, "phrain_post_id", $pi_post_id);
      // Item
      if(isset($this->data["item"])){
        foreach($this->data["item"] as $key=>$items){
          $type = isset($items["type"]) ? $items["type"] : "page" ;
          if($type=="page"){
            if($this->post_exists($items["id"])==false){
              $post = new addPost($items["id"]);
              if(isset($post->wp_post_id)){
                $this->data["item"][$key]["id"] = $post->wp_post_id;
                $this->data["item"][$key]["label"] = htmlspecialchars(stripslashes($this->data["item"][$key]["label"]));
              }
            } else {
              $this->data["item"][$key]["id"] = $this->post_exists($items["id"]);
              $this->data["item"][$key]["label"] = htmlspecialchars(stripslashes($this->data["item"][$key]["label"]));
            }
          } elseif($type=="folder") {
            foreach($items["item"] as $skey=>$item){
              $stype = isset($item["type"]) ? $item["type"] : "page" ;
              if($stype=="page"){
                if($this->post_exists($item["id"])==false){
                  $post = new addPost($item["id"]);
                  if(isset($post->wp_post_id)){
                    $this->data["item"][$key]["item"][$skey]["id"] = $post->wp_post_id;
                    $this->data["item"][$key]["item"][$skey]["label"] = htmlspecialchars(stripslashes($this->data["item"][$key]["item"][$skey]["label"]));
                  }
                } else {
                  $this->data["item"][$key]["item"][$skey]["id"] = $this->post_exists($item["id"]);
                  $this->data["item"][$key]["item"][$skey]["label"] = htmlspecialchars(stripslashes($this->data["item"][$key]["item"][$skey]["label"]));
                }
              }
            }
          }
        }
        $item = json_encode($this->data["item"], JSON_UNESCAPED_UNICODE);
        update_post_meta($this->wp_post_id, "item", $item);
      } else {
        delete_post_meta($this->wp["ID"],"item");
      }
      // Set Thumbnail
      if(isset($this->attach_id)){ set_post_thumbnail($this->wp_post_id, $this->attach_id); }
    } else { return 0; }
  }
}