<?php

error_reporting(E_ALL|E_STRICT);
include_once("/home/dan/amazon/mysql_dm.php");
//set_time_limit(-1);
define('STEP', 1);
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
function grab_product($cat_id, $web_data)
{
    //$cat_id = 10;
    $content = $web_data; //file_get_contents($file_name);
    $title_pattern = '#<a href="(.+)"><span class="srTitle">(.+)</span></a>#';
    $title_links = array();
    preg_match_all($title_pattern, $content, $title_links);
    //print_r($matches);

    $index_pattern = '#<span class="resultindex">([0-9,]+)\.</span>#';
    $indexs = array();
    preg_match_all($index_pattern, $content, $indexs);
    print_r($indexs);
    //$img_pattern = '#<img src="(.+?)" width="160" height="160">#';
    $img_pattern = '#src="(.+?)" .+ width="160" #';
    //<img src="http://ecx.images-amazon.com/images/I/31MghS-juCL._SL160_AA160_.jpg" class="" alt="Product Details" border="0" width="160" height="160">
    $imgs = array();
    preg_match_all($img_pattern, $content, $imgs);
    //print_r($imgs);

    $price_pattern = '#Buy new</a>: </span>(.+)</span>#';
    $prices = array();
    preg_match_all($price_pattern, $content, $prices);
    //print_r($prices);

    $star_pattern = '#<img.+alt="(.+?) out of 5 stars"#';
    $stars = array();
    preg_match_all($star_pattern, $content, $stars);

    $rating_pattern = '#(\d+)</a>\)</span>#';
    $ratings = array();
    preg_match_all($rating_pattern, $content, $ratings);
    //<span class="listprice">$59.99</span> <span class="saleprice">$43.99
    $link_count = count($title_links[1]);
    if ($link_count!=24)
    {
        //print_r($title_links);
        //echo $web_data;
        //die('');
        return;
    }
    //echo "$link_count";
    if (count($ratings[1]) != $link_count)
        $has_comment = 0;
    else
        $has_comment = 1;
    if (count($stars[1]) != $link_count)
    {
        $has_star = 0;
       // print_r($stars);
      //  echo $web_data;
       // die('');
    }
    else
        $has_star = 1;
    if (count($prices[1]) != $link_count)
    {
        $has_price = 0;
        //print_r($prices);
       // echo $web_data;
       // die('');
    }
    else
        $has_price = 1;

    for ($idx=0; $idx<$link_count; $idx++)
    {
      //  echo $indexs[1][$idx], ",";
        $product_name = my_escape($title_links[2][$idx]);
        $url = $title_links[1][$idx];
        $img = $imgs[1][$idx];
        if($has_star)
          $star = $stars[1][$idx];
        else
          $star = '';
        $rank = str_replace(',', '', $indexs[1][$idx]);
        $comments = '';
        if ($has_comment)
           $comments = $ratings[1][$idx];
     //   echo $prices[1][$idx]
    /*    echo $title_links[1][$idx], ",";
        echo $imgs[1][$idx], ",";
        echo $stars[1][$idx], ",";
        echo $ratings[1][$idx], ",";
        echo $prices[1][$idx], "<br>\n";*/
        
        //echo "$price<br>\n";
        $full_price = '';
        $sell_price = '';
        if ($has_price)
        {
          $pprice = $prices[1][$idx];
          $matches = array();
          if (preg_match('#<span class="listprice">\$([0-9.,]+)#', $pprice, $matches))
            $full_price = $matches[1];
          if (preg_match('#<span class="saleprice">\$([0-9.,]+)#', $pprice, $matches))
            $sell_price = $matches[1];
        }
        //echo "$idx, $listprice, $saleprice<br>\n";
        
        $sql = "insert into dm_product_link(product_name, url, cat_id, rank, full_price, sell_price, img, star, comments)values('$product_name',
        '$url', '$cat_id', '$rank', '$full_price', '$sell_price', '$img', '$star', '$comments')";
        echo "<br>$sql<br>\n";
        if (!my_query($sql))
          throw new Exception($sql, ERR_DB);
        //echo $indexs[1][$idx], ",";
    }
}
$now =date('Y/m/d H:i:s', time());
echo "Started at: $now<br>\n";
//$sql ="insert into dm_category values(297, 'Cell Phones & Accessories', NULL, NULL, '', '', 173, 50, '', '', 0, '', 1, 0, '0', NULL, NULL, 808210, 33676, 0, '', NULL, '/gp/search/ref=sr_nr_n_4?rh=n%3A172282%2Cn%3A%21493964%2Cn%3A2811119011&bbn=493964&ie=UTF8&qid=1318577199&rnid=493964', '', '', NULL)";
//my_query($sql);
$cid = 211;
if (isset($_GET['cid']))
    $cid = $_GET['cid'];
$sql = "select * from dm_category where cat_id>=$cid and page_count>last_page limit 1";
echo $sql, "<br>\n";
$rs = my_query($sql);
while ($row = mysql_fetch_assoc($rs))
{
    $page_count = $row['page_count'];
    $start = $row['last_page'];
    
    if ($page_count <= $start)
        continue;

    $cat_id = $row['cat_id'];
    $cat_name = $row['cat_name'];    
    $surl = $row['search_url'];
    $end = $start + STEP;
    echo "$page_count, $start, $end<br>\n";
    if ($end > $page_count)
        $end = $page_count;
    
    $rank = $start * 24 + 1;
    echo "$page_count, $start, $end, $rank<br>\n";
    for($idx=$start+1; $idx<=$end; $idx++)
    {
        $purl = "$surl&page=$idx";
        $filename = $cat_name."_".$idx;
        try
        {
            //echo "";
            $web_data = curl($purl);
            grab_product($cat_id, $web_data);
            //echo $web_data;
/*
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
                    grab_product($product_name, $url, $cat_id, $rank);
                    ++$rank;
                }
            }else
            {
                throw new Exception('No product list available', ERR_NO_LIST);
                //log_grab_error($filename, $url, $e);
            }*/
        }catch(Exception $e)
        {
            echo 'Message: ' .$e->getMessage();
            echo json_encode($e->getCode()), "<br>\n";
            log_grab_error($filename, $purl, $e);
        }
    }
    $now = time();
    $sql = "update dm_category set last_page=$end, date_added=$now where cat_id=$cat_id";
    echo $sql;
    my_query($sql);
    break;
//mysql_query($sql) or die(mysql_error());
}

$now =date('Y/m/d H:i:s', time());
echo "Finished at: $now<br>\n";

?>