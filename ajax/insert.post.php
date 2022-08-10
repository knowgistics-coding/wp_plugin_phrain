<?php
// pi_insert_post
function pi_insert_post()
{
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  $post = new Post();
  $post_id = $post->pi_insert_post($_POST["data"]);
  echo json_encode(get_permalink($post_id));
  die;
}
add_action('wp_ajax_nopriv_pi_insert_post', 'pi_insert_post');
add_action('wp_ajax_pi_insert_post', 'pi_insert_post');

class Post
{
  public function pi_insert_post($pi_post)
  {
    $post = $this->get_post($pi_post);
    $new_post = $this->pi_post_convert($pi_post);
    // Update or Insert Post
    if (!!$post) {
      $new_post["post"]["ID"] = $post["ID"];
      $post_id = wp_update_post($new_post["post"]);
      update_post_meta($post_id, "phrain_author", $pi_post["author"]);
      update_post_meta($post_id, "phrain_secondaryTitle", $pi_post["secondaryTitle"]);
    } else {
      $post_id = wp_insert_post($new_post["post"]);
      update_post_meta($post_id, "phrain_post_id", $pi_post["id"]);
      update_post_meta($post_id, "phrain_author", $pi_post["author"]);
      update_post_meta($post_id, "phrain_secondaryTitle", $pi_post["secondaryTitle"]);
    }
    // Set Thumbnail
    if (isset($new_post["thumbnail"]) ? !!$new_post["thumbnail"] : false) {
      set_post_thumbnail($post_id, $new_post["thumbnail"]);
    } else {
      delete_post_thumbnail($post_id);
    }
    // Set Map
    if (isset($pi_post["map"])) {
      update_post_meta($post_id, "map", json_encode($pi_post["map"], JSON_UNESCAPED_SLASHES));
    }
    if (isset($pi_post["attach"])) {
      if (isset($pi_post["attach"]["photo"])) {
        update_post_meta($post_id, "gallery", json_encode($pi_post["attach"]["photo"]));
      }
    }
    return $post_id;
  }
  protected function get_post($pi_post)
  {
    if (!isset($pi_post["id"])) {
      return false;
    }
    $args = array(
      'post_type' => array('post'),
      'meta_query' => array(
        'relation' => 'OR',
        array("key" => "phrain_post_id", "value" => "$pi_post[id]", "compare" => "LIKE"),
      ),
      'post_status' => 'any',
      'posts_per_page' => -1,
    );
    $the_query = new WP_Query($args);
    $post = null;
    if (!!$the_query->post) {
      $raw = $the_query->post;
      $post = array(
        "ID"    => $raw->ID,
        "title" => get_the_title($raw->ID),
        "url"   => get_permalink($raw->ID),
      );
    }
    return $post;
  }
  protected function pi_post_convert($pi_post)
  {
    // Date
    $date = !!$pi_post["edit"] ? $pi_post["edit"] : $pi_post["date"];
    $date = strlen($date) > 10 ? ceil($date / 1000) : $date;
    // Post
    $post = array(
      "post_date"     => date("Y-m-d H:i:s", $date),
      "post_content"  => $this->parse_content_post($pi_post["content"]),
      "post_title"    => $pi_post["label"],
      "post_status"   => "publish",
      "post_type"     => "post",
      "post_category" => $this->category_filter($pi_post["cat"]),
    );
    // Author
    $users = !!get_option("pi_user") ? json_decode(get_option("pi_user"), true) : array();
    if (isset($users[$pi_post["user"]])) {
      $post["post_author"] = $users[$pi_post["user"]]["wp_ID"];
    }
    // Thumbnail
    $attach_id = $this->pi_add_photo($pi_post);
    return array(
      "post"      => $post,
      "thumbnail" => $attach_id,
    );
  }
  protected function parse_content_post($content)
  {
    $new = $content;
    preg_match_all("/<iframe(.*)<\/iframe>/i", $new, $match);
    if (!empty($match) ? isset($match[0]) : false) {
      foreach ($match[0] as $k => $v) {
        preg_match_all("/src=(.*)\"/i", $v, $src);
        if (isset($src[1])) {
          $url = preg_replace("/[\\\\\"]/i", "", $src[1][0]);
          $new = str_ireplace($v, "[embed]" . $url . "[/embed]", $new);
        }
      }
    }
    return $new;
  }
  protected function category_filter($catag)
  {
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
      return null;
    }
  }

  // ==================================================
  // Image Processor
  // ==================================================
  protected function pi_add_photo($pi_post)
  {
    if (!isset($pi_post["cover"])) {
      return false;
    }
    $content = file_get_contents($pi_post["cover"]);
    $md5 = md5($content);

    $attach_id = $this->get_attachment_id($md5);
    if ($attach_id == null) {
      $path = wp_upload_dir()["path"] . "/$md5.jpg";
      file_put_contents($path, $content);
      $attachment = array(
        'post_mime_type' => "image/jpeg",
        'post_title' => sanitize_file_name("$md5.jpg"),
        'post_content' => '',
        'post_status' => 'inherit'
      );
      $attach_id = wp_insert_attachment($attachment, $path);
      $attach_data = wp_generate_attachment_metadata($attach_id, $path);
      $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
    }
    if (strpos($pi_post["cover"], "firebasestorage")) {
      $exp = preg_split("/%2F|\.jpg/", $pi_post["cover"]);
      if (isset($exp["1"]) ? strlen($exp["1"]) == 32 : false) {
        $photoDB = json_decode(file_get_contents("https://johnjadd-3524a.firebaseio.com/photoDB/$exp[1].json"), true) ?: array();
        if (isset($photoDB["credit"])) {
          update_post_meta($attach_id, 'credit', $photoDB["credit"]);
        }
        if (isset($photoDB["web"])) {
          update_post_meta($attach_id, 'web', esc_url($photoDB["web"]));
        }
      }
    }
    return $attach_id;
  }
  public function get_attachment_id($md5)
  {
    global $wpdb;
    $query = "SELECT ID FROM {$wpdb->posts} WHERE guid like '%" . $md5 . "%'";
    return $wpdb->get_var($query);
  }
}
