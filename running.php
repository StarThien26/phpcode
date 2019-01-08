<?php
ini_set('max_execution_time', 0);
header('Content-Type: text/html; charset=utf-8');
require_once './config_server.php';
//====================== DEVELOPED WITH <3 BY _Neiht =======================//
echo '<pre>';
$current = time();
$rs = mysqli_query($conn, "SELECT count(id) as total FROM vip_react_running_post");
$row = mysqli_fetch_assoc($rs);
$total_id = $row['total'];
$limit_id = 50; // số id chạy trên mỗi file
$current_cron = $_GET['cron'];
$total_cron = ceil($total_id / $limit_id);
$start = ($current_cron - 1) * $limit_id;
$data = array();
echo "Tổng File Cron Là: $total_cron ".basename($_SERVER['PHP_SELF'])."?cron=[1,2,3...]";
echo '<br>';


$vip = mysqli_query($conn, "SELECT * FROM vip_react_running_post WHERE '$current'-`created_time`>`runafter` AND '$current' -`timeupdate`>`sleep`*60 AND running = 0 AND status_post = 'RUNNING' LIMIT $start,$limit_id");
if (mysqli_num_rows($vip) == 0) die('Empty ^^');
while ($d = mysqli_fetch_assoc($vip)){
    $data[] = $d;
}
shuffle($data);
$table = rand_table_token();
reset_token($table);
$ACCESS_TOKEN = liveToken($table);
if ($vip && $ACCESS_TOKEN) {
	foreach ($data as $key => $row) {
        if($key >= 20) break;
		$TOKEN = array();
		$sttID = $row['id_post'];
		if ($row['type_post'] != 'Bài Viết' && $row['type_post'] != 'Video Live Stream') {
			$sttID = explode('_', $sttID)[1];
		}
		$limitLike = $row['limit_like'];
		$speedLike = $row['speed_like'];
		$get_info_post = get_info_post($sttID, $ACCESS_TOKEN);
		$countLike = $get_info_post['reactions']['summary']['total_count'];
		if ($countLike >= $limitLike) {
			update_done($sttID);
			continue;
		}
		$likeConLai = $limitLike - $countLike;
		$num_token = 0;
		$running = running($sttID, 0, time(), $countLike);
		if ($likeConLai < $speedLike) {
			if ($likeConLai <= 0) {
				//============= ĐỦ LIKE =============//
				echo 'ID_POST: <b>'.$sttID.'</b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Trạng Thái: <b style="color: #62e262;">OK</b><br />';
			} else {
				//============ Số Like Còn Thiếu $likeConLai ==========//
				echo 'ID_POST: <b>'.$sttID.'</b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Số Like Còn Thiếu: <b style="color: red;">'.$likeConLai.'</b><br />';
				$num_token = $likeConLai;
			}
		} else {
			//============= Chưa Đủ Like - Chạy Bình Thường $speedLike =================//
			echo 'ID_POST: <b>'.$sttID.'</b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Trạng Thái: <b style="color: green;">Đang Chạy...</b><br />';
			$num_token = $speedLike;
		}
		//========== Send Request To Server FB =========//
		$cr = 0;
		$res = get_tokens_random($num_token, $table);
		while ($_N = mysqli_fetch_assoc($res)) {
			$hasreaction = hasreaction($_N['access_token'], $sttID);
			if ($hasreaction == 'NONE') {
				$type = randreactions($row['camxuc']);
				$reaction = reaction($_N['access_token'], $sttID, $type);
				if ($reaction == 'ok') {
					has_used($_N['access_token'], $table);
					$cr++;
				} elseif ($reaction == 'tokendie') {
					$delete = delete_token($_N['access_token'], $table);
				}
			} elseif ($hasreaction == 'idsttdie') {
				$update = update_post('DIE', $sttID);
				break;
			} elseif ($hasreaction == 'tokendie') {
				$delete = delete_token($_N['access_token'], $table);
			} else {
				// Token đã like post
			}
		}
		$running = running($sttID, 0, time(), ($countLike+$cr));
		//////////////XỬ LÝ QUA SERVER TRUNG GIAN ///////////////////
		/*$post_data = array(
		    'time_delay' => 500,
		    'id' => $sttID,
		    'typeReact' => explode('|', $row['camxuc']),
		    'access_token' => $TOKEN
		);
		if (count($TOKEN) > 0) {
			$_Neiht->setType('Auto-React-Custom');
			$_Neiht->setPostData($post_data);
			$_Neiht->execCurl();
			$response = $_Neiht->getResponse();
			var_dump($response);
		}*/
		/////////////////////////////////////////////////////////////////////
		//========== Send Request To Server FB =========//
	}
}
die('_Neiht');

//============= FUNCTION =========================/////
function hasreaction($token, $idstt){
	$token = trim($token);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://graph.facebook.com/v2.9/$idstt/reactions?summary=true&limit=0&access_token=$token",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache"
      ),
    ));
    $data = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($data,true);
    if ($data['error']['code'] == '190') {
    	return "tokendie";
    }
    if($data['error']['code']=='100' || $data['error']['code']=='200'){
        return "idsttdie";
    }
    if ($data['summary']['viewer_reaction'] == 'NONE') {
    	return 'NONE';
    } else {
    	return 'reacted';
    }
}
function update_done($idpost){
	global $conn;
	mysqli_query($conn, "UPDATE vip_react_running_post SET status_post = 'DONE' WHERE id_post = '$idpost'");
}
function delete_token($token, $table){
	global $conn;
    return mysqli_query($conn, "DELETE FROM $table WHERE access_token = '$token'");
}
function reaction($token,$idstt,$type){
    $token = trim($token);
    $idstt = trim($idstt);
    $type = trim($type);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://graph.facebook.com/$idstt/reactions?access_token=$token",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => "type=$type",
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
        "content-type: application/x-www-form-urlencoded"
      ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response,true);
    if(isset($response['success'])){
    	return 'ok';
    } else {
    	if ($data['error']['code'] == '190') {
    		return "tokendie";
	    }
	    if($data['error']['code']=='100' || $data['error']['code']=='200'){
	        update_post('DIE', $idstt);
	    }
    }
}
function update_post($status_post, $id_post){
	global $conn;
	$result = mysqli_query($conn, "UPDATE vip_react_running_post SET status_post = '$status_post' WHERE id_post = '$id_post'");
}
function running($id_post, $a, $current, $countLike){
	global $conn;
	$result = mysqli_query($conn, "UPDATE vip_react_running_post SET running = $a, timeupdate = '$current', count_like = '$countLike' WHERE id_post = '$id_post'");
}
function get_info_post($id_post, $token){
	$get = json_decode(file_get_contents("https://graph.facebook.com/$id_post/?fields=reactions.summary(true).limit(5000)&access_token=$token"), true);
	if ($get['created_time']) {
		return $get;
	}
}
function checkToken($token){
	$get = json_decode(file_get_contents('https://graph.facebook.com/me/?access_token='.$token.'&field=id'), true);
	if ($get['id']) {
		return 1;
	}
	return 0;
}
function check_running_post($idpost){
	global $conn;
    $result = mysqli_query($conn, "SELECT id FROM vip_react_running_post WHERE id_post = '$idpost'");
    if(mysqli_num_rows($result) > 0 ) return 1;
    return 0;
}
function add_running_post($fbid, $name, $name_buy, $sttID, $countLike, $message, $speedLike, $limitLike, $camxuc){
	global $conn;
	$result = mysqli_query($conn, "INSERT INTO vip_react_running_post (fbid, name, name_buy, id_post, count_like, message, speed_like, limit_like, camxuc, running) VALUES ('$fbid', '$name', '$name_buy', '$sttID', '$countLike', '$message', '$speedLike', '$limitLike', '$camxuc', 0)");
	if ($result) return 1;
	return 0;
}
function update_running_post($id_post, $message, $speedLike, $limitLike, $camxuc){
	global $conn;
	$result = mysqli_query($conn, "UPDATE vip_react_running_post SET message = '$message', speed_like = '$speedLike', limit_like = '$limitLike', camxuc = '$camxuc' WHERE id_post = '$id_post'");
}
function liveToken($table){
	$tokens = get_tokens_random(10, $table);
	while ($token = mysqli_fetch_assoc($tokens)) {
		$checkToken = checkToken($token['access_token']);
		if ($checkToken == 1) {
			$ACCESS_TOKEN = $token['access_token'];
			return $ACCESS_TOKEN;
		}
	}
}
function count_time_to_current_in_day($now){
    $date = DateTime::createFromFormat("d/m/Y", $now);
    $year = $date->format("Y");
    $month = $date->format("m");
    $day = $date->format("d");
    $dt = $day . "-" . $month . "-" . $year . " 00:00:00";
    $d = new DateTime($dt, new DateTimeZone('Asia/Ho_Chi_Minh'));
    return $d->getTimestamp();
}
function get_tokens_random($limit, $table){
    global $conn;
    return mysqli_query($conn, "SELECT  access_token FROM $table WHERE has_used = 0 ORDER BY RAND() LIMIT ".$limit);
}
function rand_table_token(){
	global $conn;
    $result =  mysqli_query($conn, "SELECT * FROM manage_access_token WHERE vip = 'checked' ORDER BY RAND() LIMIT 1");
    return mysqli_fetch_assoc($result)['table_token'];
}
function has_used($token, $table){
	global $conn;
    return mysqli_query($conn, "UPDATE $table SET has_used = 1 WHERE access_token = '$token'");
}
function reset_token($table){
	global $conn;
    $result = mysqli_query($conn, "SELECT id FROM $table WHERE has_used = 0");
    if(mysqli_num_rows($result) < 50 ){
    	return mysqli_query($conn, "UPDATE $table SET has_used = 0");
    }
}
function count_react($post_id, $token){
    $get_json = json_decode(file_get_contents('https://graph.facebook.com/'.$post_id.'/reactions?summary=true&access_token='.$token),true);
    if($get_json['summary']['total_count']){
        return $get_json['summary']['total_count'];
    } else {
        return 0;
    }
}
function randreactions($data){
    $data = explode('|',$data);
    $cd = count($data);
    $r = rand(0,$cd-1);
    return $data[$r];
}
//============================= END =============================/////////////
?>