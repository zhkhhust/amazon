<?php

error_reporting(E_ALL|E_STRICT);
include_once("/home/dan/amazon/mysql_dm.php");
//set_time_limit(-1);
define('STEP', 80);
define('ERR_DB', 2);
define('ERR_NO_LIST', 3);
function save_product_link($product_name, $url, $type, $rank)
{
    $product_name = my_escape($product_name);
    $url = my_escape(urldecode($url));
    $sql = "insert into dm_product_link(product_name, url, cat_id, rank) values('$product_name', '$url', $type, $rank)";
    //return my_query($sql);
    if (!my_query($sql))
        throw new Exception($sql, ERR_DB);
}
$now =date('Y/m/d H:i:s', time());
echo "Started at: $now<br>\n";
//$sql ="insert into dm_category values(297, 'Cell Phones & Accessories', NULL, NULL, '', '', 173, 50, '', '', 0, '', 1, 0, '0', NULL, NULL, 808210, 33676, 0, '', NULL, '/gp/search/ref=sr_nr_n_4?rh=n%3A172282%2Cn%3A%21493964%2Cn%3A2811119011&bbn=493964&ie=UTF8&qid=1318577199&rnid=493964', '', '', NULL)";
//my_query($sql);
$sql = "select * from dm_category where cat_id=297 limit 1";
$rs = my_query($sql);
while ($row = mysql_fetch_assoc($rs))
{
    $page_count = $row['page_count'];
    $start = $row['last_page'];
    //echo "$page_count, $start<br>\n";
    if ($page_count <= $start)
        continue;

    $cat_id = $row['cat_id'];
    $cat_name = $row['cat_name'];    
    $surl = $row['search_url'];
    $end = $start + STEP;
    if ($end > $page_count)
        $end = $page_count;
    //echo "$start, $end<br>\n";
    $rank = $start * 24 + 1;
    for($idx=$start+1; $idx<=$end; $idx++)
    {
        $purl = "http://www.amazon.com$surl&page=$idx";
        $filename = $cat_name."_".$idx;
        try
        {
            //echo "";
            $web_data = curl($purl);
            //echo $web_data;
            $pattern = '#<a href="(.+?)"><span class="srTitle".*?>(.+?)</span></a>#';
            if (preg_match_all($pattern, $web_data, $matches))
            {
                //if (count($matches[1]) == 23)
                //    echo $web_data;
                //echo "\n\n";
                //print_r($matches);
                for ($idx2 = 0; $idx2<count($matches[1]); $idx2++)
                {
                    $url = urldecode($matches[1][$idx2]);
                    $product_name = $matches[2][$idx2];
                    save_product_link($product_name, $url, $cat_id, $rank);
                    ++$rank;
                }
            }else
            {
                throw new Exception('No product list available', ERR_NO_LIST);
                //log_grab_error($filename, $url, $e);
            }
        }catch(Exception $e)
        {
            echo 'Message: ' .$e->getMessage();
            echo json_encode($e->getCode()), "<br>\n";
            log_grab_error($filename, $url, $e);
        }
    }
    $now = time();
    $sql = "update dm_category set last_page=$end, date_added=$now where cat_id=$cat_id";
    my_query($sql);
    break;
//mysql_query($sql) or die(mysql_error());
}

$now =date('Y/m/d H:i:s', time());
echo "Finished at: $now<br>\n";

?>