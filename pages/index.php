<?php require_once(PHRAIN_PATH."inc/header.php"); ?>

<form action="javascript:void(0)" onsubmit="return register()" style="text-align:center;padding:0 1rem;margin:3rem 0;">
  <input id="loggin_email" name="email" type="text" placeholder="E-mail" required>
  <input id="loggin_pass" name="pass" type="password" placeholder="Password" required>
  <input class="button button-primary" type="submit" value="Register">
</form>

<table class="widefat fixed" cellspacing="0" style="max-width:640px;margin:0 auto;">
  <thead>
    <tr>
      <th class="manage-column column-columnname" scope="col" style="width:80px;"></th>
      <th id="columnname" class="manage-column column-columnname" scope="col"><b>Phra.in E-mail</b></th>
      <th id="columnname" class="manage-column column-columnname" scope="col"><b>Wordpress User</b></th>
    </tr>
  </thead>
  <tbody>
<?php
$users = !!get_option("pi_user") ? json_decode(get_option("pi_user"),true) : array() ;
foreach($users as $id=>$user){
  echo '<tr class="alternate">
    <td class="column-columnname"><button class="button button-danger" onclick="unregister(\''.$user["pi_ID"].'\',\''.$user["pi_email"].'\')">Delete</button></td>
    <td class="column-columnname">'.$user["pi_email"].'</td>
    <td class="column-columnname">'.get_user_by("ID",$user["wp_ID"])->user_nicename.'</td>
  </tr>';
}
?>
  </tbody>
</table>

<script>
const register = ()=>{
  let email = document.getElementById("loggin_email").value;
  let pass = document.getElementById("loggin_pass").value;
  if(!!email && !!pass){
    firebase.auth().signInWithEmailAndPassword(email,pass).then(snap=>{
      $.post(ajaxurl,{
        action:"register_phrain_user",
        data:{ ID:snap.user.uid, email:snap.user.email }
      },(res)=>{
        if(!!res){
          let url = "<?php echo get_site_url(); ?>";
          firebase.database().ref(`users/${snap.user.uid}/pi_sync/`+MD5(url)).set(url).then(()=>{
            firebase.auth().signOut().then(()=>{
              alert('Register Success!!');
              location.reload();
            });
          });
        } else {
          alert('Error!!');
          console.log(res);
        }
      });
    }).catch(err=>{ console.log(err); if(!!err.message){ alert(err.message) } });
  }
}
const unregister = (pi_user_ID,pi_email)=>{
  if(confirm("คุณต้องการลบหรือไม่?")){
    let pass = prompt(`ใส่รหัสผ่านสำหรับ "${pi_email}"`);
    if(!!pass){
      $.post(ajaxurl,{
        action:"unregister_phrain_user",
        data:pi_user_ID,
      },(res)=>{
        if(!!res){
          let url = "<?php echo get_site_url(); ?>";
          firebase.auth().signInWithEmailAndPassword(pi_email,pass).then(()=>{
            firebase.database().ref(`users/${pi_user_ID}/pi_sync/`+MD5(url)).remove().then(()=>{
              firebase.auth().signOut().then(()=>{
                location.reload();
              });
            });
          })
        } else {
          alert('Error!!');
          console.log(res);
        }
      });
    }
  }
}
</script>