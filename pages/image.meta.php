<script src="https://www.gstatic.com/firebasejs/5.7.2/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/5.7.2/firebase-auth.js"></script>
<script src="https://www.gstatic.com/firebasejs/5.7.2/firebase-database.js"></script>
<script>
  // Initialize Firebase
  var config = {
    apiKey: "AIzaSyA0fkeZmoVeTDLnrAYpZaOvcrQmIUT4dEI",
    authDomain: "johnjadd-3524a.firebaseapp.com",
    databaseURL: "https://johnjadd-3524a.firebaseio.com",
    projectId: "johnjadd-3524a",
    storageBucket: "johnjadd-3524a.appspot.com",
    messagingSenderId: "1091789743614"
  };
  firebase.initializeApp(config);
</script>
<script id="angular-js" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.7.5/angular.min.js"></script>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">

<div class="wrap" ng-app="main-app" ng-controller="main-ctrl">
  <div style="text-align:right;" ng-if="!!fbs.user">
    <button class="button button-primary">
      <i class="fas fa-user"></i>
      <span ng-bind="fbs.user.displayName || fbs.user.email"></span>
    </button>
    <button class="button button-default" ng-click="fbs.logout()">
      <i class="fas fa-sign-out-alt"></i>
    </button>
  </div>
  <form style="text-align:right" ng-submit="fbs.login(email,pass)" ng-if="!!fbs.user==false">
    <input ng-model="email" type="text" placeholder="E-mail" autocomplete="off">
    <input ng-model="pass" type="password" placeholder="Password" autocomplete="off">
    <button type="submit" class="button button-primary"><i class="fas fa-sign-in-alt"></i> Login</button>
  </form>
  <div style="padding-top:1rem;">
    <table border="1" style="border-collapse:collapse;width:100%;">
      <thead><tr>
        <th>ID</th>
        <th>post_title</th>
        <th>post_type</th>
        <th>post_modified</th>
        <th>post_thumbnail</th>
      </tr></thead>
      <tbody><tr align="center" ng-repeat="post in fbs.wp_posts">
        <td ng-bind="post.ID"></td>
        <td align="left">
          <a ng-href="<?php echo get_site_URL(); ?>/?p={{ post.ID }}" target="_blank" ng-bind="post.post_title"></a>
        </td>
        <td ng-bind="post.post_type"></td>
        <td ng-bind="post.post_modified"></td>
        <td><a ng-href="<?php echo get_site_URL(); ?>/wp-admin/upload.php?item={{ post.thumbnail }}" target="_blank">Cover Link</a></td>
      </tr></tbody>
    </table>
  </div>
  <div style="margin-top:1rem;">
    <button class="button button-primary" ng-click="fbs.batch()">Batch Sync</button>
  </div>
  <div style="margin-top:1rem;"><div ng-repeat="line in fbs.sync_result" ng-bind="line"></div></div>
</div>

<?php
$args = array(
  'post_type' => 'any',
  'orderby' => 'ID',
  'order' => 'asc',
  'meta_query' => array(
    'relation' => 'AND',
    array( "key" => "phrain_post_id", "compare" => "EXISTS" ),
    array( "key" => "_thumbnail_id", "compare" => "EXISTS" ),
  ),
  'posts_per_page' => -1,
);
$posts = wp_get_recent_posts($args,ARRAY_A);
foreach($posts as $key=>$post){
  $posts[$key]["thumbnail"] = get_post_thumbnail_id($post["ID"]);
  $posts[$key]["phrain_id"] = get_post_meta($post["ID"],"phrain_post_id",true);
}
?>

<script>
const app = angular.module("main-app",[]);
const wp_posts = <?php echo json_encode($posts); ?>;
app.controller("main-ctrl", ["$scope","$sce",function($scope,$sce){
  $scope.fbs = new FBS($scope,$sce);
}]);
function FBS($scope,$sce){
  this.wp_posts = wp_posts;
  this.sync_result = [];
  // ================================================== Get Phra.in Post ==================================================
  this.get_pi_post = (post)=>{
    return new Promise((resolved,rejected) => {
      const db = {
        post: "ebook/page",
        book: "ebook/book",
      };
      firebase.database().ref(`${db[post.post_type]}/${post.phrain_id}`).once("value", snap => {
        let pi_post = snap.val();
        if( !!pi_post ? !!pi_post.cover : false ){
          firebase.database().ref('photoDB').orderByChild('2048').equalTo(pi_post.cover).once("value", snapimg => {
            resolved(post);
            if(!!snapimg){
              img = Object.values(snapimg.val())[0];
            } else {
              this.sync_result.push(`ID ${post.ID} : No photo data.`);
              $scope.$apply();
              rejected();
            }
            let confirm_text;
            if( !!img.web ){
              confirm_text = `ต้องการอัพเดท เว็บไซต์ ของรูปปกบทความ "${ post.post_title }" หรือไม่?`;
            } else if( !!img.credit ){
              confirm_text = `ต้องการอัพเดท เครดิต ของรูปปกบทความ "${ post.post_title }" หรือไม่?`;
            }
            if( !!confirm_text ){
              jQuery.post(ajaxurl,{
                action: "update_image_meta",
                data: img,
              },res => {
                this.sync_result.push(`ID ${post.ID} : ${res.message}`);
                $scope.$apply();
                resolved(res);
              }).fail(err => console.log(err));
            } else {
              this.sync_result.push(`ID ${post.ID} : ไม่มีข้อมูลเว็บไซต์ หรือเครดิต`);
              $scope.$apply();
              rejected();
            }
          });
        } else {
          this.sync_result.push(`ID ${post.ID} : No Post in Phra.in or No Cover.`);
          rejected();
          $scope.$apply();
        }
      });
    });
  };
  // ================================================== Batch ==================================================
  this.batch = ()=>{
    this.sync_result = [];
    const sync = (k=0)=>{
      if( !!this.wp_posts[k] ){
        this.get_pi_post( this.wp_posts[k] )
          .then(res => {
            sync(k+1);
          }).catch(() => {
            sync(k+1);
          });
      } else { alert('Sync Success.'); }
    };
    sync();
  }; 
  // ================================================== Login ==================================================
  this.login = (email,pass)=>{
    firebase.auth().signInWithEmailAndPassword(email,pass).then(user => {
      this.user = user;
    }).catch(err => {
      alert(err.message);
    });
  };
  // ================================================== Logout ==================================================
  this.logout = ()=>{ firebase.auth().signOut(); };
  // ================================================== Auth Check ==================================================
  firebase.auth().onAuthStateChanged(user => {
    this.user = user;
    $scope.$apply();
  });
}
</script>