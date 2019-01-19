<?php
ini_set('max_execution_time', 0);
header('Content-Type: text/html; charset=utf-8');
require_once './config_server.php';
require_once './curl_function.php';
$server = $_GET['sv'];
echo '<pre>';
$h_now  = date("H");
if($h_now >= 23|| $h_now <= 5) die('Stoped...');
$date = date("H:i d/m/Y");
$current = time();
$result = mysqli_query($conn, "SELECT * from vip_bot WHERE type_access = 'ACCESS_TOKEN' AND action = 'checked' AND '$current' < time_buy+limit_time*2592000 AND sv = '$server' ORDER BY RAND() LIMIT 0,20");
if (mysqli_num_rows($result) === 0) die('Empty!!');
$rand = mt_rand(1,3);
while ($row = mysqli_fetch_assoc($result)) {
	$getHome = json_decode(__run('https://graph.facebook.com/me/home?fields=id,from,reactions.summary(true)&limit='.$rand.'&access_token='.$row['access_token']), true);
	if (count($getHome['data']) > 0) {
		foreach ($getHome['data'] as $key => $act) {
			if (!isset($act['from']['category']) && $act['reactions']['summary']['viewer_reaction'] == 'NONE') {
				$arr_type = explode('|', $row['type_react']);
	            $type = trim($arr_type[array_rand($arr_type)]);
	            sleep(mt_rand(5,10));
				$reacted = json_decode(__run('https://graph.fb.me/' . $act['id'] . '/reactions?access_token=' . $row['access_token'] . '&type=' . $type . '&method=post'), true);
				var_dump($reacted);
			}
		}
	}
}
?>