<?php

system ("clear");
error_reporting(0);
date_default_timezone_set("Asia/Jakarta");
//collor
/*
\033[1;90m Abu Gelap
\033[1;91m Merah
\033[1;92m Hijau
\033[1;93m Kuning
\033[1;94m Biru Gelap
\033[1;95m Ungu
\033[1;96m Biru Telor Asin
\033[1;97m Putih
*/
$ab="\033[1;90m";
$m="\033[1;91m";
$h="\033[1;92m";
$k="\033[1;93m";
$bg="\033[1;94m";
$u="\033[1;95m";
$bta="\033[1;96m";
$p="\033[1;97m";
//MODUL
function post($link,$data,$ua){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $link);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $ua);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return curl_exec($ch);
  }
function get($link,$ua){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $link);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $ua);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	  curl_setopt($ch, CURLOPT_HEADER,0);
	  
    return curl_exec($ch);
  }
/*  function Save($namadata){
   if(file_exists($namadata)){
     $data = file_get_contents($namadata);
    }else{
     $data = readline("\033[1;32m Input ".$namadata." :  ");
     file_put_contents($namadata,$data);
    }
    return $data;
  } */
  function g(){
    return "\033[1;97m______________________________________________________________\n";
  }
  function tim($tim){
  for($i=$tim; $i>0; $i--){
    echo "\033[1;97m🖕DAGOAN \033[1;90m$i \033[1;97mKEDENG DA🖕";
    sleep(1);
    echo "                     \r";
  }
  return $i;
  }
 
  $cook = getenv('LOGIN');
  if (!$cook) {
    $cook = readline('cookie: ');
    }
  if (!$cook) {exit;
    }
  $uagent = file(__DIR__ . '/../USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
  $user = $uagent[array_rand($uagent)];
  echo "set UA => $user\n";
  $ua=array("host: coinads.net","content-type: text/html; charset=UTF-8","user-agent: $user","cookie: $cook");
  $res=get("https://coinads.net/dashboard",$ua);
  $bal=explode('</h2>',explode('<h2>',$res)[1])[0];
  $tanggal = date("d-m-Y");
$jam = date("H:i:s");
$geo = json_decode(file_get_contents("http://ip-api.com/json"), true);
$lokasi = $geo['country'] . " / " . $geo['regionName'] . " / " . $geo['city'];
echo g();
  echo $m;
  echo "    ___   __                                  
   /   | / /____  ____  ___ _      ___      __
  / /| |/ __/ _ \/_  / / _ \ | /| / / | /| / /
 / ___ / /_/  __/ / /_/  __/ |/ |/ /| |/ |/ / 
/_/  |_\__/\___/ /___/\___/|__/|__/ |__/|__/  
                                               \n";
  echo g();
  echo $m."BOT          ".$p."\n";
  echo $m."TIME         :".$p."$jam\n";
  echo $m."DATE         :".$p."$tanggal\n";
  echo $m."LICATION     :".$p."$lokasi\n";
  echo g();
  echo $m."Main Balance : ".$p."$bal\n";
  echo g();
  a:
  $res=get("https://coinads.net/smm/watch",$ua);
  $res=get("https://coinads.net/smm/watch",$ua);
  #$tim=explode(' sec <sup><small class="badge badge-success">+0% bonus</small></sup></div>',explode('<div class="btn badge-info w-100"> 1 Energy, 1 EXP, 20 Token per ',$res)[1])[0];
  $tim=60;
  if($tim){
    tim($tim);
  }
  $res=get("https://coinads.net/smm/api/claim_watch/7",$ua);
  $suc=explode('}',explode('{',$res)[1])[0];
  echo $h."$suc\n";
  if($suc == '"status":"error","message":"Sadece 1 taray\u0131c\u0131dan izleme yapa bilirsiniz!","refresh":true,"captcha":false'){
        for($i=120; $i>0; $i--){
    echo "\033[1;97m🖕DAGOAN \033[1;90m$i \033[1;97mKEDENG DA🖕";
    sleep(1);
    echo "                     \r";
    }
    echo g ();
    goto a;
  }
  $res=get("https://coinads.net/dashboard",$ua);
  $bal=explode('</h2>',explode('<h2>',$res)[1])[0];
  echo $m."Main Balance : ".$p."$bal\n";
  echo g();
  goto a;
