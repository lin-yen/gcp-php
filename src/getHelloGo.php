<?php
  // creat curl connect
  $ch = curl_init();

  // set url，內部互打用 go 的 container NAME (gcp service NAME)
  curl_setopt($ch, CURLOPT_URL, "http://go-service:4000/say-hello");
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  // get
  $temp=curl_exec($ch);

  // close
  curl_close($ch);

  echo $temp;
?>
