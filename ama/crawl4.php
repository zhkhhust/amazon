<?php
error_reporting(E_ALL|E_STRICT);
include_once("mysql_dm.php");
include_once("db_util.php");
$pageSource = "";
$idx = 0;

define('TBL_PRODUCT',        'dm_product');
define('TBL_RELATED_ITEM',   'dm_product_related');
define('TBL_BUY_ALSO',       'dm_product_buy_also');
define('TBL_BUY_TOGETHER',   'dm_product_buy_together');
define('TBL_BUY_AFTER_VIEW', 'dm_product_buy_after_view');
define('TBL_SELLER',         'dm_seller');

echo date('Y-m-d H:i:s', time()), "<br>\n";
function getCurrentTime() {
    return time();
}
function getColor($pageSource)
{
    $start = strpos($pageSource, '<select name="childVariationASIN"  >' );
    $end = strpos($pageSource, '</select>', $start);
    $pattern = '#<option value=".+?".+?title="(.+?)">#';
    
    if (preg_match_all($pattern, $pageSource, $matches))
    {
        return implode(', ', $matches[1]);
    }
    return '';
    //print_r($matches);
}
function getTitle($pageSource) {
    $title_pattern = '#<title>(.+?)</title>#';
    $title_links = array();
    if (preg_match($title_pattern, $pageSource, $title_links))
        return $title_links[1];
    return "";
}

function getKeywords($pageSource) {
    $strPattern = "#<meta name=\"keywords\" content=\"(.+)\" />#";
    return getMatchedText($pageSource, $strPattern);
}

function getDescription($pageSource) {
    $strPattern = "#<meta name=\"description\" content=\"(.+)\" />#";
    return getMatchedText($pageSource, $strPattern);
}

function getProducer($pageSource) {
    $strPattern = "#by <a .+\">(.+)</a>#";
    return getMatchedText($pageSource, $strPattern);
}

function getProductName($pageSource) {
    $beginIndex = strpos($pageSource, "<h1 class=\"parseasinTitle\">");
    if ($beginIndex === false)
        return "";
    $endIndex = strpos($pageSource, "</h1>", $beginIndex);
    if ($endIndex === false)
        return "";
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    $strPattern = "#<span id=\"btAsinTitle\" style=\"\">(.+)</span>#";
    return getMatchedText($pageSource, $strPattern);
}

function getPrice2($pageSource) {
    $beginIndex = strpos($pageSource, "<span id=\"pricePlusShippingQty\">");
    if ($beginIndex === false) {
       // echo("beginIndex err");
        return "";
    }
    $endIndex = strpos($pageSource, "</b>", $beginIndex);
    if ($endIndex === false) {
        //echo("endIndex err");
        return "";
    }

    $pageSource = substr($pageSource, $beginIndex, $endIndex + 4 - $beginIndex);
    //echo($pageSource);
    $strPattern = "#<b class=\"price\">\\$(.+)</b>#";
    return getMatchedText($pageSource, $strPattern);
}

function getPrice($pageSource) {
    $beginIndex = strpos($pageSource, "<td class=\"priceBlockLabel\">List Price:</td>");
    if ($beginIndex === false) {
        //echo("beginIndex err");
        return getPrice2($pageSource);
    }
    $endIndex = strpos($pageSource, "</tr>", $beginIndex);
    if ($endIndex === false) {
        //echo("endIndex err");
        return "";
    }

    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    //echo($pageSource);
    $strPattern = '#<span.+?class=\"listprice\">\$(.+)</span>#';
    //<span id="listPriceValue"  class="listprice">$49.95</span>
    return getMatchedText($pageSource, $strPattern);
    //return $pageSource;
}

function getMatchedText($source, $strPattern) {
    $matches = array();
    if (preg_match($strPattern, $source, $matches)) {
        return trim($matches[1]);
    }
    return "";
}

function stringToInt($strInt) {
    return trim(str_replace(',', '', $strInt));
}

function getItemCount($pageSource) {
    $result = "";

    $itemCountStartPattern = "<div id=\"resultCount\" class=\"resultCount\">";
    $start = strpos($pageSource, $itemCountStartPattern);
    //echo "120 $start<br>\n";
    if ($start === false) {
        $strPattern = '#condition=new">(.+?)new</a></span>#';
        return getMatchedText($pageSource, $strPattern);
    }

    $start += strlen($itemCountStartPattern);
    $end = strpos($pageSource, "</div>", $start);
    $pageSource = trim(substr($pageSource, $start, $end));

    $strPattern = "#<span>Showing (\\d+) Result[s]</span>#";
    $result = getMatchedText($pageSource, $strPattern);
    if (result . equals("")) {
        $strPattern = "#<span>Showing \\d+ - \\d+ of (\\d+) Result[s]</span>#";
        $result = getMatchedText($pageSource, $strPattern);
    }
    if ($result == "")
        return 0;
    return trim($result);
}

function getProductBreadCrumb($pageSource) {
    $beginIndex = strpos($pageSource, "<h2>Look for Similar Items by Category</h2>");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "<li>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += strlen('<li>');
    $endIndex = strpos($pageSource, "</li>", $beginIndex);
    if ($endIndex === false)
        return "";
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    //$pattern = '#<a href=".+?">.+?</a>#';
    //$matches = array();
    //preg_match_all($pattern, $pageSource, $matches);
    $pattern = '#<a href=".+?">#';
    $pageSource = preg_replace($pattern, '', $pageSource);
    $pageSource = str_replace('</a>', '', $pageSource);
    return $pageSource;
    //return getBreadCru($pageSource);
}

function getTechDetail($pageSource) {
    $beginIndex = strpos($pageSource, "<h2>Technical Details</h2>");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "<li>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 4;
    $endIndex = strpos($pageSource, "</ul>", $beginIndex);
    if ($endIndex === false)
        return "";
    return substr($pageSource, $beginIndex, $endIndex - $beginIndex);
}
function getProductDimensionWeight($pageSource) {
    $beginIndex = strpos($pageSource, "Product Dimensions:");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "</b>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 4;
    $endIndex = strpos($pageSource, "</li>", $beginIndex);
    if ($endIndex === false)
        return array('', '');
    $dimension_weight = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    $dimension_weight = explode('; ', $dimension_weight);
    $dimension_weight = array_map ('trim', $dimension_weight);
   
    return $dimension_weight;
}

function getProductWeight($pageSource) {
    $weights = array();
    $pattern = '#Shipping Weight:</b> (.+?)\s+\(#';
    if (preg_match_all($pattern, $pageSource, $weights)) {
        //var_dump($weights);
        return $weights[1][0];
    }
    return "";
}

function getProductASIN($pageSource) {
    $asins = array();
    $pattern = '#<li><b>ASIN:</b> (.+?)</li>#';
    if (preg_match_all($pattern, $pageSource, $asins)) {
        //var_dump($weights);
        return $asins[1][0];
    }
    return "";
}

function getProductMarketDate($pageSource) {
    $beginIndex = strpos($pageSource, "Date first available at Amazon.com:");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "</b>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 4;
    $endIndex = strpos($pageSource, "</li>", $beginIndex);
    if ($endIndex == -1)
        return "";
    return substr($pageSource, $beginIndex, $endIndex - $beginIndex);
}

function getProductModel($pageSource) {
    $beginIndex = strpos($pageSource, "Item model number:");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "</b>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 4;
    $endIndex = strpos($pageSource, "</li>", $beginIndex);
    if ($endIndex === false)
        return "";
    return substr($pageSource, $beginIndex, $endIndex - $beginIndex);
}

function getProductSN($pageSource) {
    return getProductASIN($pageSource) + " " + getProductModel($pageSource);
}

function getRelatedItems($pageSource, $pid)
{
    //$startStr = 
    $start = strpos($pageSource, 'Related Items');
    $end = strpos($pageSource, 'Tags Customers Associate with This Product');
    if ($end === false)
    {
        $end = strpos($pageSource, 'Suggested Tags from Similar Products');
        if ($end === false)
            return;
    }
    $pageSource = substr($pageSource, $start, $end-$start);
    $lines = explode("\n", $pageSource);
    $start_pattern = '#<div class="acc-product">#';
    $url_title_pattern = '#<a href="(.+?)" title="(.+?)">#';
    $img_pattern = '#<img src="(.+?)" width="100"#';
    $star_pattern = '#<span>([0-9.]+) out of 5 stars</span>#';
    $view_pattern = '#([0-9,]+)</a>\)</span>#';
    $list_price_pattern = '#<span class="listprice">\$([0-9,.]+)</span>#';
    $price_pattern = '#<span class="price">\$([0-9,.]+)</span>#';
    $seller_pattern = '#In Stock from <a href="(.+?)">(.+?)</a>#';
    $matches = array();
    $item = null;
    foreach ($lines as $line)
    {        
        if (preg_match($start_pattern, $line))
        {
            if ($item)
                save_object(TBL_RELATED_ITEM, $item);
            $item = array('pid'=>$pid);
        }
        else if (preg_match($url_title_pattern, $line, $matches))
        {
            $item['url'] = $matches[1];
            $item['title'] = $matches[2];
        }else if (preg_match($img_pattern, $line, $matches))
        {
            $item['image'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($star_pattern, $line, $matches))
        {
            $item['star'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($list_price_pattern, $line, $matches))
        {
            $item['list_price'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($price_pattern, $line, $matches))
        {
            $item['price'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($seller_pattern, $line, $matches))
        {
            $item['seller_name'] = $matches[2];
            //$item['seller_url'] = $matches[1];
            $seller_id = save_seller($matches[2], $matches[1]);
            $item['seller_id'] = $seller_id;
            //$item['title'] = $matches[2];
        }
        if (preg_match($view_pattern, $line, $matches))
        {
            $item['view'] = str_replace(',', '', $matches[1]);   
        }
    }
    save_object(TBL_RELATED_ITEM, $item);
}
function getAlsoBuy($pageSource, $pid) {
    $datas = array();
    $recommendations = array();
    $beginIndex = strpos($pageSource, "Customers Who Bought This Item Also Bought");
    if ($beginIndex === false)
        return $recommendations;
    $endIndex = strpos($pageSource, "</ul>", $beginIndex);
    if ($endIndex === false)
        return $recommendations;

    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    //echo $pageSource;
    $lines = explode("\n", $pageSource);
    $url_title_pattern = '#<a href="(.+?)"  title="(.+?)"  class="sim-img-title" >#';
    $img_pattern = '#<img src="(.+?)" width="100"#';
    $star_pattern = '#<span>(.+?) out of 5 stars</span>#';
    $view_pattern = '#([0-9,]+)</a>#';
    $price_pattern = '#<span class="price">\$(.+?)</span>#';
    $producer_pattern = '#<span class="shvl-byline">by (.+?)</span>#';
    $item = null;
    $matches = array();
    foreach ($lines as $line)
    {
        if (preg_match($url_title_pattern, $line, $matches) )
        {
            if ($item)
                save_object(TBL_BUY_ALSO, $item);
                //$datas[] = $item;
            $item = array('pid'=>$pid, 'url'=>$matches[1], 'title'=>$matches[2]);
            //$item['url'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($img_pattern, $line, $matches))
        {
            $item['image'] = $matches[1];
        }else if (preg_match($star_pattern, $line, $matches))
        {
            $item['star'] = $matches[1];
        }else if (preg_match($price_pattern, $line, $matches))
        {
            $item['price'] = $matches[1];
        }else if (preg_match($producer_pattern, $line, $matches))
        {
            $item['producer_name'] = $matches[1];
        }
        if (preg_match($view_pattern, $line, $matches))
        {
            $item['view'] = str_replace(',', '', $matches[1]);
        }
    }
    save_object(TBL_BUY_ALSO, $item);
    //$datas[] = $item;
    //var_dump($datas);
    return $datas;
}
function get_similar_url($pageSource)
{
    $pattern = '#<a href="(.+?)">Explore similar items</a>#';
    return getMatchedText($pageSource, $pattern);
}
function getCustomerBuyAfterView($pageSource, $pid) {
    $datas = array();
    $recommendations = array();
    $beginIndex = strpos($pageSource, "What Other Items Do Customers Buy After Viewing This Item");
    if ($beginIndex === false)
        return $recommendations;
    $endIndex = strpos($pageSource, "</ul>", $beginIndex);
    if ($endIndex === false)
        return $recommendations;

    $link = '';
   // $pattern = '#<a href="(.+?)">Explore similar items</a>#';
   // if (preg_match_all($pattern, $pageSource, $recommendations))
   //     $link = $recommendations[1][0];
    //$sql = "update dm_product set similar_url='$link'";
    //if (!my_execute($sql))
     //   echo $sql, "<br>\n";
    
    $url_imgs = array();
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    $lines = explode("\n", $pageSource);
    $url_img_pattern = '#<a href="(.+?)"\s+id=".+?" > <img src="(.+?)" width="50"#';
    $title_pattern = '#<span class="cpAsinTitle">(.+?)</span>#';
    $star_pattern = '#<span>(.+?) out of 5 stars</span>#';
    $view_pattern = '#([0-9,]+)</a>#'; //771</a>)</span>
    $price_pattern = '#<span class="price">\$(.+?)</span>#'; 
    $seller_pattern = '#<span class="vtp-byline-text">by (.+?)</span>#'; 
    $item = null;
    $matches = array();
    foreach ($lines as $line)
    {
        if (preg_match($url_img_pattern, $line, $matches) )
        {
            if ($item)
                save_object(TBL_BUY_AFTER_VIEW, $item);
                //$datas[] = $item;
            $item = array('pid'=>$pid);
            $item['url'] = $matches[1];
            $item['image'] = $matches[2];
        }else if (preg_match($title_pattern, $line, $matches))
        {
            $item['title'] = $matches[1];
            
        }else if (preg_match($star_pattern, $line, $matches))
        {
            $item['star'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($price_pattern, $line, $matches))
        {
            $item['price'] = $matches[1];
            //$item['title'] = $matches[2];
        }else if (preg_match($seller_pattern, $line, $matches))
        {
            $item['producer_name'] = $matches[1];
        }
        if (preg_match($view_pattern, $line, $matches))
        {
            $item['view'] = str_replace(',', '', $matches[1]);
            //$item['title'] = $matches[2];
        }
    }
    save_object(TBL_BUY_AFTER_VIEW, $item);
    //$datas[] = $item;
    //var_dump($datas);
    return $datas;
}

function getProductStars($pageSource) {
    $pattern = '#<span class="swSprite s_star_4_0\s*" title="([0-9.]+) out of 5 stars"#';
    $matches = array();
    if (preg_match($pattern, $pageSource, $matches))
        return $matches[1];
    return '';
}

function get_tags($pageSource)
{
    $start = strpos($pageSource, 'Tags Customers Associate with This Product');
    $end = strpos($pageSource, 'Add your first tag');
    $pageSource = substr($pageSource, $start, $end-$start);
    $pattern = '#<span class="tag"><a href="(.+?)" title="([0-9,]+?) customers? tagged this product \'(.+?)\'."#';
    $matches = array();
    preg_match_all($pattern, $pageSource, $matches);
    $tags = implode(', ', $matches[3]);
    
    //var_dump($matches);

    $pattern ='#<a href="(.+?)">See all ([0-9,]+) tags#';
    if (preg_match($pattern, $pageSource, $matches) )
    {
        $tag_link = $matches[1];
        $tags_count = $matches[2];
    }else
    {
        $tag_link = '';
        $tags_count = 0;
    }
 //   var_dump($matches);
    //$tag_link = 
    return array($tags, $tag_link, $tags_count);
}

function getProductViewers($pageSource) {
    $pattern = '#([0-9,]+) customer reviews</a>\)#';
    $reviews = array();
    if (preg_match($pattern, $pageSource, $reviews))
        return stringToInt($reviews[1]);
    //if ($endIndex === false)
    return 0;
    //return stringToInt(substr($pageSource, $beginIndex+1, $endIndex));
}

function getProductPraises($pageSource) {
    $strPattern = "#<span class=\"amazonLikeCount\">(\\d+)</span>#";
    return getMatchedText($pageSource, $strPattern);
}

function getBuyTogether($pageSource, $pid) {
    $datas = array();
    $recommendations = array();
    $beginIndex = strpos($pageSource, "Frequently Bought Together");
    if ($beginIndex === false)
        return $recommendations;
    $endIndex = strpos($pageSource, "What Other Items Do Customers Buy After Viewing This Item", $beginIndex);
    if ($endIndex === false)
        return $recommendations;
    
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    
    
    $lines = explode("\n", $pageSource);
    $pattern = '#<a href=".+?"><img src="(.+?)" width="75"#';
    if (!preg_match_all($pattern, $pageSource, $recommendations))
       return;
    $price_pattern = '#<span class="price bxgy-item-price">\$(.+?)</span>#';
    $producer_pattern = '#<span class="bxgy-byline-text">by (.+?)</span>#'; 
    $url_title_pattern = '#<span id=bxgy_[yz]_title><a href="(.+?)">(.+?)</a>#';

    $seller_pattern = '#Ships from and sold by <strong><a href="(.+?)">(.+?)</a>#';
    $item = null;
    $matches = array();

    $idx = 0;
    foreach ($lines as $line)
    {
       if (preg_match($url_title_pattern, $line, $matches))
        {
           
            if ($item)
                save_object(TBL_BUY_TOGETHER, $item);
               //var_dump($item);
            $item = array('pid'=>$pid);
            $item['url'] = $matches[1];
            $item['title'] = $matches[2];
            //var_dump($matches);
        }
        if (preg_match($seller_pattern, $line, $matches))
        {            
            $idx++;
            if ($idx >2 && $idx%2)
            {
                //var_dump($matches);
                //$item['seller_url'] = $matches[1];
                $item['seller_name'] = $matches[2];
                $seller_id = save_seller($matches[2], $matches[1]);
                $item['seller_id'] = $seller_id;
            }           
        }
        else if (preg_match($price_pattern, $line, $matches))
        {
            if ($item)
                $item['sell_price'] = $matches[1];
        }
        if (preg_match($producer_pattern, $line, $matches))
        {
            if ($item)
                $item['producer_name'] = $matches[1];
            //var_dump($matches);
        }
    }
    save_object(TBL_BUY_TOGETHER, $item);
    //var_dump($item);
    //die("");
    //return $datas;
}

function getNormalText($htmlText) {
    $the = $htmlText;

    $the = str_replace("&gt;", ">", $the);
    $the = str_replace("&lt;", "<", $the);
    $the = str_replace("&nbsp;", " ", $the);
    $the = str_replace("&quot;", "\"", $the);
    $the = str_replace("&#39;", "\'", $the);
    $the = str_replace("&#160;", " ", $the);
    $the = str_replace("&ldquo;", "“", $the);
    $the = str_replace("&rdquo;", "”", $the);
    $the = str_replace("&#8212;", "——", $the);
    $the = str_replace("&#8226;", "•", $the);
    $the = str_replace("&acute;", "'", $the);
    $the = str_replace("&amp;", "&", $the);
    return trim($the);
}

function getMarketPrice($pageSource)
{
    $pattern = '#<span id="listPriceValue"\s+class="listprice">\$([0-9,.]+)</span>#';
    return getMatchedText($pageSource, $pattern);
}
function processProductPage($pageSource, $id, $cat_id) {
    if (strlen($pageSource) < 200) {
        return null;
    }

    $pageSource = getNormalText($pageSource);
    $title = getTitle($pageSource);
    $path = getProductBreadCrumb($pageSource);
    $goodsName = getProductName($pageSource);
    $keywords = getKeywords($pageSource);
    $desc = getDescription($pageSource);
    $tech_detail = getTechDetail($pageSource);
    //$goodsSn = getProductSN($pageSource);
    $dimension_weight = getProductDimensionWeight($pageSource);
    if (isset($dimension_weight[0]))
        $dimension = $dimension_weight[0];
    else
        $dimension = '';
    if (isset($dimension_weight[1]))
        $net_weight = $dimension_weight[1];
    else
        $net_weight = 0;
    $similar_url = get_similar_url($pageSource);
    $star = getProductStars($pageSource);
    $viewer = getProductViewers($pageSource);
    $providerName = getProducer($pageSource);
    $productWeight = getProductWeight($pageSource);
    $price = getPrice($pageSource);
    $market_price = getMarketPrice($pageSource);
    $goods_number = getItemCount($pageSource);
    $tags = get_tags($pageSource);
    $model = getProductModel($pageSource);
    $asin = getProductASIN($pageSource);
    $color = getColor($pageSource);

    $product = array(
        'id'=>$id,
        'cat_id' => $cat_id, 'asin' => $asin, 'goods_name' => $goodsName, 'dimension' => $dimension, 'net_weight'=>$net_weight,
        'click_count' => $viewer, 'brand_id' => 0, 'producer_name' => $providerName, 'tech_detail'=>$tech_detail, 'model'=> $model,
        'goods_number' => $goods_number, 'goods_weight' => $productWeight, 'market_price' => $market_price, 'shop_price' => $price,
        'promote_price' => 0, 'keywords' => $keywords, 'goods_desc' => $desc, 'goods_thumb' => $path, 'star'=>$star,
        'goods_img' => '', 'site_url' => '', 'title' => $title, 'path' => $path, 'date_added' => time(),'similar_url'=>$similar_url,
        'tags' =>$tags[0], 'tag_url'=>$tags[1],   'tag_count'=>$tags[2]
    );
    save_product($product);
     //   var_dump($product);
    getRelatedItems($pageSource, $id);
    getBuyTogether($pageSource, $id);
    getCustomerBuyAfterView($pageSource, $id); 
    getAlsoBuy($pageSource, $id);
    return;
}

function setUp($fileName) {
    $pageSource = getNormalText(file_get_contents($fileName));
}

function test() {
    $index = 0;


    /* echo("\n**********" + (++index) + "Geting Bread Cru**********");
      $breadCru = getBreadCru($pageSource);
      echo(breadCru);

      echo($strpos(0,  20));
      echo("\n**********" + (++index) + " Geting Bread Crumb**********");
      $breadCrumb = getBreadCrumb($pageSource);
      echo(breadCrumb[0] + ", " + breadCrumb[1]);
      echo($strpos(0,  20)); */
    $pageSource = file_get_contents('product1.htm');

    ++$index;
    echo "\n**********$index: Geting Description**********", "<br>\n";
    $description = getDescription($pageSource);
    echo $description, "<br>\n";

    ++$index;
    echo "\n**********$index: Geting Item Count**********", "<br>\n";
    $itemCount = getProductBreadCrumb($pageSource);
    echo $itemCount, "<br>\n";

    ++$index;
    echo "\n**********$index: Geting Producer**********", "<br>\n";
    $keywords = getProducer($pageSource);
    echo $keywords, "<br>\n";

    ++$index;
    echo "\n**********$index: Geting Price**********", "<br>\n";
    $market_price = getPrice($pageSource);
    echo $market_price, "<br>\n";

    ++$index;
    echo "\n**********$index: Geting Product Name**********", "<br>\n";
    $product_name = getProductName($pageSource);
    echo $product_name, "<br>\n";

    ++$index;
    echo "\n**********$index Get Product Weight**********", "<br>\n";
    $weight = getProductWeight($pageSource);
    echo $weight, "<br>\n";
    //foreach ($products as $product)
    //	echo $product, "<br>\n";
    ++$index;
    echo "\n**********$index: Geting Title**********", "<br>\n";
    $title = getTitle($pageSource);
    echo $title, "<br>\n";

    echo "\n********** Geting Together Products**********", "<br>\n";
    $togetherProducts = getTogetherProducts($pageSource);
    foreach ($togetherProducts as $product)
        echo $product[0], ", ", $product[2], ", ", $product[2], "<br>\n";

    echo "\n********** Geting Together Products2**********", "<br>\n";
    $togetherProducts = getTogetherProducts2($pageSource);

    //foreach ($togetherProducts as $product)
    //	echo $product[0], ", ", $product[2], ", ", $product[2], "<br>\n";

    echo "\n********** Geting Together Products3**********", "<br>\n";
    $togetherProducts = getTogetherProducts3($pageSource);
    var_dump($togetherProducts);
    //foreach ($togetherProducts as $product)
    //	echo $product[0], ", ", $product[2], ", ", $product[2], "<br>\n";

    echo "\n********** Geting Product ASIN**********", "<br>\n";
    $asin = getProductASIN($pageSource);
    echo $asin, "<br>\n";

    echo "\n********** Geting Product Dimensions**********", "<br>\n";
    $productDimensions = getProductDimensions($pageSource);
    echo $productDimensions, "<br>\n";

    echo "\n********** Geting Product Detail**********", "<br>\n";
    $detail = getTechDetail($pageSource);
    echo $detail, "<br>\n";

    echo "\n********** Geting Product Model**********", "<br>\n";
    $detail = getProductModel($pageSource);
    echo $detail, "<br>\n";

    echo "\n********** Geting Product Market Date**********", "<br>\n";
    $detail = getProductMarketDate($pageSource);
    echo $detail, "<br>\n";

    echo "\n********** Geting Product Stars**********", "<br>\n";
    $detail = getProductStars($pageSource);
    echo $detail, "<br>\n";

    echo "\n********** Geting Product Viewers**********", "<br>\n";
    $detail = getProductViewers($pageSource);
    echo $detail, "<br>\n";

    echo "\n********** Geting Product Praises**********", "<br>\n";
    $detail = getProductPraises($pageSource);
    echo $detail, "<br>\n";

    /* 	
      echo("\n**********" + (++index) + " getProductBreadCrumb**********", "<br>\n";
      $productBreadCrumb = getProductBreadCrumb($pageSource);
      echo(productBreadCrumb, "<br>\n";

      echo("\n**********" + (++index) + " getProductDetail**********", "<br>\n";
      $productDetail = getProductDetail($pageSource);
      echo(productDetail, "<br>\n";

      echo("\n**********" + (++index) + " getProductDimensions**********", "<br>\n";
      $productDimensions = getProductDimensions($pageSource);
      echo(productDimensions, "<br>\n";

      echo("\n**********" + (++index) + " getProductModel**********", "<br>\n";
      $productModel = getProductModel($pageSource);
      echo(productModel, "<br>\n";

      echo("\n**********" + (++index) + " getProductPraises**********", "<br>\n";
      $productPraises = getProductPraises($pageSource);
      echo(productPraises, "<br>\n";

      echo("\n**********" + (++index) + " getProductSN**********", "<br>\n";
      $productSN = getProductSN($pageSource);
      echo(productSN, "<br>\n";

      echo("\n**********" + (++index) + " getProductStarDetail**********", "<br>\n";
      $productStarDetail = getProductStarDetail($pageSource);
      for(star: productStarDetail)
      {
      echo(star, "<br>\n";
      }
      //echo(productStarDetail);

      echo("\n**********" + (++index) + " getProductStars**********", "<br>\n";
      $productStars = getProductStars($pageSource);
      echo(productStars, "<br>\n";

      echo("\n**********" + (++index) + " getProductViewers**********", "<br>\n";
      $productViewers = getProductViewers($pageSource);
      echo(productViewers, "<br>\n";

      echo("\n**********" + (++index) + " getProductWeight**********", "<br>\n";
      $productWeight = getProductWeight($pageSource);
      echo(productWeight, "<br>\n"; */
}

$sql = "select id, url, cat_id from dm_product_link where grabbed=0 limit 50";
$rs = my_query($sql) or die(my_error());
while ($row = mysql_fetch_row($rs))
{
    $id = $row[0];
    $url = $row[1];
    $cat_id = $row[2];
    try {
    $web_data = curl($url); 
    //echo $web_data;
}catch(Exception $e)
        {
            echo 'Message: ' .$e->getMessage();
            echo json_encode($e->getCode()), "<br>\n";
            log_grab_error($id, $url, $e);
            $sql = "update dm_product_link set grabbed=2 where id=$id ";
            //echo "$sql, $url<br>\n";
            my_execute($sql);
            continue;
        }
    processProductPage($web_data, $id , $cat_id);
    $sql = "update dm_product_link set grabbed=1 where id=$id ";
    //echo "$sql, $url<br>\n";
    my_execute($sql);
    sleep(2);
}
echo date('Y-m-d H:i:s', time()), "<br>\n";
/*
$pageSource = file_get_contents('product1.htm');
$pageSource = getNormalText($pageSource);
processProductPage($pageSource, 1, 1);

*/

//processProductPage($pageSource, 1 , 1);

/*echo "\n\nRelated item";

echo "\n\ngetTogetherProducts_1";

echo "\n\ngetCustomerBuyAfterView"; */
//getCustomerBuyAfterView($pageSource, 1); 


//getAlsoBuy($pageSource, 1);
//getTogetherProducts_1($pageSource, 1);
//test();
?>