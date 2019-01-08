<?php
error_reporting(0);
$config_db = array(
	'db_host' => '139.99.44.50',
	'db_user' => 'root',                      
	'db_name' => 'data_vip',
	'db_pass' => 'matkhau123'
);
$conn = mysqli_connect($config_db['db_host'], $config_db['db_user'], $config_db['db_pass'], $config_db['db_name']);
mysqli_set_charset($conn,"utf8");
?>