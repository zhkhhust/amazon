<?php

error_reporting(E_ALL|E_STRICT);
include_once("/home/dan/amazon/mysql_dm.php");
/*$url = 'http://www.amazon.com/BlackBerry-Bold-9900-Unlocked-Phone/dp/B0058GWR8O/ref=sr_1_18/183-7883071-2328606?ie=UTF8&s=electronics&qid=1318607938&sr=1-18';

if (isset($_GET['url']))
    $url = $_GET['url'];*/

$id = 1;
if (isset($_GET['id']))
    $id = $_GET['id'];
$sql = 'select url from dm_product where id='.$id;
$rs = my_query($sql);
//while()
$row = mysql_fetch_assoc($rs);
$url = $row['url'];
$web_data = curl($url);
echo $web_data;

?>