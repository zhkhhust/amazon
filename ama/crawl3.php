<?php
error_reporting(E_ALL|E_STRICT);
include_once("mysql_dm.php");

$pageSource = "";
$idx = 0;

function form_insert($fields, $data) {

    $result = "insert into team(begin_time, end_time, title, image, now_number, market_price, team_price, from_url) values(";
    if (!is_array($fields))
        $fields = explode(', ', $fields);
    $data['title'] = str_replace(array('<![CDATA[', '！]]>'), '', $data['title']);

    foreach ($fields as $field)
        $result .= "'" . mysql_real_escape_string($data[$field]) . "', ";
    $result = substr($result, 0, -2);
    return $result . ");";
}

function insert_product($data) {
    $fields = array_keys($data);
    $field_list = implode('`, `', $fields);
    $field_list = '`' . $field_list . '`';
    $data = array_map('mysql_real_escape_string', $data);
    $values = array_values($data);
    $value_list = implode("', '", $values);
    $value_list = "'" . $value_list . "'";
    $sql = "insert into dm_goods($field_list) values($value_list)";
   // echo $sql, "<br>\n";
    if (!my_execute($sql))
        echo $sql, "<br>\n";
    $now = time();
    $id = $data['id'];
    $sql = "update dm_product set grabbed=1, grab_time=$now where id=$id";
   // echo $sql;
    if (!my_execute($sql))
        echo $sql, "<br>\n";
    //return $value_list;
}

function transform_array(&$data, $from, $to) {
    if (!is_array($from))
        $from = explode(', ', $from);
    if (!is_array($to))
        $to = explode(', ', $to);
    for ($idx = 0; $idx < count($from); $idx++)
        $data[$to[$idx]] = $data[$from[$idx]];
    //startTime, endTime, title, image, bought, value, price, loc
}

function dateToInt($date) {
    return $date;
}

function getCurrentTime() {
    return time();
}

function getTitle($pageSource) {
    $title_pattern = '#<title>(.+?)</title>#';
    $title_links = array();
    preg_match_all($title_pattern, $pageSource, $title_links);
    //var_dump($title_links);
    //return "";
    return $title_links[1][0];
}

function getKeywords($pageSource) {
    $strPattern = "#<meta name=\"keywords\" content=\"(.+)\" />#";
    //echo($pageSource);
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
    } else {
        //echo(source);
        //echo(strPattern);
    }
    return "";
}

function stringToInt($strInt) {
    //return trim(str_replace(',' '', $strInt));
    return $strInt;
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

function getProductDimensions($pageSource) {
    $beginIndex = strpos($pageSource, "Product Dimensions:");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "</b>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 4;
    $endIndex = strpos($pageSource, "</li>", $beginIndex);
    if ($endIndex === false)
        return "";
    return trim(substr($pageSource, $beginIndex, $endIndex - $beginIndex));
}

function getProductWeight($pageSource) {
    $weights = array();
    $pattern = '#Shipping Weight:</b> ([0-9,.]+)#';
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

function getProductStars($pageSource) {
    $beginIndex = strpos($pageSource, "<span class=\"crAvgStars\"");
    if ($beginIndex === false)
        return "";
    $beginIndex = strpos($pageSource, "<span>", $beginIndex);
    if ($beginIndex === false)
        return "";
    $beginIndex += 6;
    $endIndex = strpos($pageSource, "</span>", $beginIndex);
    if ($endIndex === false)
        return "";
    return substr($pageSource, $beginIndex, $endIndex - $beginIndex);
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

/*
  function getProductStarDetail($pageSource)
  {
  $details = new int[5];
  begin;
  end;

  $begin = strpos("5 star");
  $begin ==-1)
  {
  echo("getProductStarDetail beginidx err 1");
  return details;
  }
  $begin = strpos("<td align=\"right\" class=\"tiny\"", begin);
  $begin ==-1)
  {
  echo("getProductStarDetail beginidx err 2");
  return details;
  }
  $begin = strpos("(", begin);
  $end = strpos(")", begin);
  details[0] = stringToInt($strpos(begin+1, end));

  $begin = strpos("4 star", begin);
  $begin = strpos("<td align=\"right\" class=\"tiny\"", begin);
  $begin ==-1)
  {
  return details;
  }
  $begin = strpos("(", begin);
  $end = strpos(")", begin);
  details[1] = stringToInt($strpos(begin+1, end));

  $begin = strpos("<td align=\"right\" class=\"tiny\"", begin);
  $begin ==-1)
  {
  return details;
  }
  $begin = strpos("(", begin);
  $end = strpos(")", begin);
  details[2] = stringToInt($strpos(begin+1, end));

  $begin = strpos("<td align=\"right\" class=\"tiny\"", begin);
  $begin ==-1)
  {
  return details;
  }
  $begin = strpos("(", begin);
  $end = strpos(")", begin);
  details[3] = stringToInt($strpos(begin+1, end));

  $begin = strpos("<td align=\"right\" class=\"tiny\"", begin);
  $begin ==-1)
  {
  return details;
  }
  $begin = strpos("(", begin);
  $end = strpos(")", begin);
  details[4] = stringToInt($strpos(begin+1, end));
  //echo("Details 1: " + details[1]);
  //echo("Details 2: " + details[2]);
  //echo("Details 3: " + details[3]);
  //echo("Details 4: " + details[4]);
  return details;
  } */
/*
  function getSimilarProducts($pageSource)
  {
  $beginIndex = 0;
  nextIndex;
  //endIndex;
  //end;
  $index = 0;
  //recommended;
  $recommendations = new [5];
  $strPattern = "<a class=\"pairImgTitle\" href=\".+\" title=\"(.+)\">";
  $prefix = "<a class=\"pairImgTitle\"";

  do{
  $beginIndex = strpos(prefix, beginIndex);
  //echo(beginIndex);
  $beginIndex == -1)
  return recommendations;
  //beginIndex += prefix.length();
  $nextIndex = strpos("<a class=\"pairImgTitle\"", beginIndex + prefix.length());
  $nextIndex ==-1)
  $nextIndex = $pageSource.length();
  recommendations[index++] = getMatchedText($strpos(beginIndex, beginIndex+300), strPattern);
  $beginIndex = nextIndex;
  $index == 2)
  //	echo($strpos(beginIndex, beginIndex+200) + ", " + strPattern);
  }while(true);
  //return recommendations;

  } */


function getTogetherProducts($pageSource, $cat_id) {
    $recommendations = array();
    $beginIndex = strpos($pageSource, "Frequently Bought Together");
    if ($beginIndex === false)
        return $recommendations;
    $endIndex = strpos($pageSource, "</table>", $beginIndex);
    if ($endIndex === false)
        return $recommendations;
    $pattern = '#<a href="(.+?)"><img src="(.+?)" width="75" alt="(.+?)"#';
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    preg_match_all($pattern, $pageSource, $recommendations);

    $datas = array();
    $count = count($recommendations[1]);
    for ($idx = 0; $idx < $count; ++$idx) {
        $url = $recommendations[1][$idx];
        $pname = my_escape($recommendations[3][$idx]);
        $datas[] = array($recommendations[1][$idx], $recommendations[2][$idx], $recommendations[3][$idx]);
        $sql = "insert into dm_together1(cat_id, pname, url) values($cat_id, '$pname', '$url')";
        if (!my_execute($sql))
            echo $sql, "<br>\n";
    }
    return $datas;
    /*
      recommendations[0] = strpos(beginIndex+1, endIndex).trim();

      $beginIndex = strpos("<ul tabindex");
      $end = strpos("</ul>", beginIndex);
      //echo($strpos(beginIndex, end));
      while (beginIndex < end)
      {
      $beginIndex = strpos("<li>", beginIndex);
      $beginIndex ==-1 || beginIndex>end)
      {
      break;
      }
      $beginIndex = strpos("<a href=", beginIndex);
      $endIndex =  strpos("</a>", beginIndex);
      $beginIndex = $pageSource.lastIndexOf(">", endIndex);
      recommendations[++index] = strpos(beginIndex+1, endIndex).trim();
      //echo($strpos(beginIndex+1, endIndex));
      $beginIndex = endIndex + 4;
      }
      $idx =0; idx<recommendations.length; idx++)
      {
      if( recommendations[idx]!=null && recommendations[idx].length()>512)
      recommendations[idx] = recommendations[idx].substr(0, 509) + "...";
      }
      return recommendations;
     */
}

function getTogetherProducts2($pageSource, $cat_id) {
    $datas = array();
    $recommendations = array();
    $beginIndex = strpos($pageSource, "What Other Items Do Customers Buy After Viewing This Item");
    if ($beginIndex === false)
        return $recommendations;
    $endIndex = strpos($pageSource, "</ul>", $beginIndex);
    if ($endIndex === false)
        return $recommendations;

    $link = '';
    $pattern = '#<a href="(.+?)">Explore similar items</a>#';
    if (preg_match_all($pattern, $pageSource, $recommendations))
        $link = $recommendations[1][0];
    $sql = "update dm_product set similar_url='$link'";
    if (!my_execute($sql))
        echo $sql, "<br>\n";
    
    $url_imgs = array();
    $pageSource = substr($pageSource, $beginIndex, $endIndex - $beginIndex);
    $pattern = '#<a href="(.+?)"\s+id=".+?" > <img src="(.+?)" width="50"#';
    preg_match_all($pattern, $pageSource, $url_imgs);
    //var_dump($recommendations);

    $titles = array();
    $pattern = '#<span class="cpAsinTitle">(.+?)</span>#';
    preg_match_all($pattern, $pageSource, $titles);
    //$pattern = '#<span class="vtp-byline-text">by (.+?)</span>#';
    //preg_match_all($pattern, $pageSource, $recommendations);

    $stars = array();
    $pattern = '#<span>(.+?) out of 5 stars</span>#';
    preg_match_all($pattern, $pageSource, $stars);
    $views = array();
    $pattern = '#([0-9,]+)</a>#';
    preg_match_all($pattern, $pageSource, $views);
    $prices = array();
    $pattern = '#<div class="price">(.+?)</div>#';
    preg_match_all($pattern, $pageSource, $prices);

    $count = count($url_imgs[1]);
    if (count($stars[1]) != $count)
        $has_star = 0;
    else
        $has_star = 1;
    if (count($views[1]) != $count)
        $has_view = 0;
    else
        $has_view = 1;
    if (count($prices[1]) != $count)
        $has_price = 0;
    else
        $has_price = 1;
    $star = '';
    $view = '';
    $price = '';
    //$count = count($recommendations[1]);
    for ($idx = 0; $idx < $count; ++$idx) {
        $url = $url_imgs[1][$idx];
        $img = $url_imgs[2][$idx];
        $pname = my_escape($titles[1][$idx]);
        if ($has_star)
            $star = $stars[1][$idx];
        if ($has_view)
            $view = $views[1][$idx];
        if ($has_price)
            $price = $prices[1][$idx];
        $sql = "insert into dm_together2(cat_id, pname, url, img, star, view, price) values($cat_id, '$pname', '$url',
               '$img', '$star', '$view', '$price')";
        if (!my_execute($sql))
            echo $sql, "<br>\n";
        
        $datas[] = array($titles[1][$idx], $url, $img, $star,
            $view, $price);
    }
    return $datas;
}

function getTogetherProducts3($pageSource, $cat_id) {
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
    //return $recommendations;

    $url_titles = array();
    $pattern = '#<a href="(.+?)"  title="(.+?)"  class="sim-img-title" >#';
    preg_match_all($pattern, $pageSource, $url_titles);
    //   var_dump($url_titles);

    $imgs = array();
    $pattern = '#<img src="(.+?)" width="100"#';
    preg_match_all($pattern, $pageSource, $imgs);
    //var_dump($imgs);
    //$pattern = '#<span class="vtp-byline-text">by (.+?)</span>#';
    //preg_match_all($pattern, $pageSource, $recommendations);

    $stars = array();
    $pattern = '#<span>(.+?) out of 5 stars</span>#';
    preg_match_all($pattern, $pageSource, $stars);
    // var_dump($stars);

    $views = array();
    $pattern = '#([0-9,]+)</a>#';
    preg_match_all($pattern, $pageSource, $views);
    //var_dump($views);
    $prices = array();
    $pattern = '#<span class="price">(.+?)</span>#'; //<span class="price">$2.93</span>
    preg_match_all($pattern, $pageSource, $prices);
    // var_dump($prices);


    $count = count($url_titles[1]);
    if (count($stars[1]) != $count)
        $has_star = 0;
    else
        $has_star = 1;
    if (count($views[1]) != $count)
        $has_view = 0;
    else
        $has_view = 1;
    if (count($prices[1]) != $count)
        $has_price = 0;
    else
        $has_price = 1;
    $star = '';
    $view = '';
    $price = '';
    //$count = count($recommendations[1]);
    for ($idx = 0; $idx < $count; ++$idx) {
        $url = $url_titles[1][$idx];
        $img = $imgs[1][$idx];
        $pname = my_escape($url_titles[2][$idx]);
        if ($has_star)
            $star = $stars[1][$idx];
        if ($has_view)
           $view = $views[1][$idx];
        if ($has_price)
            $price = $prices[1][$idx];
        $sql = "insert into dm_together3(cat_id, pname, url, img, star, view, price) values($cat_id, '$pname', '$url',
               '$img', '$star', '$view', '$price')";
        if (!my_execute($sql))
            echo $sql, "<br>\n";
        
        $datas[] = array($url_titles[1][$idx], $imgs[1][$idx], $url_titles[2][$idx], $star,
            $view, $price);
    }
    return $datas;
}

function getListBreadCru($pageSource) {
    $crumbs = getBreadCrumb($pageSource);
    return $crumbs[1] . "," . $crumbs[0];
}

/*
  function getBreadCru($pageSource)
  {
  $linkBegin = 0;
  $linkEnd = 0;
  $end = $pageSource.length();
  $buffer = new Buffer();
  result;

  while(linkBegin<end)
  {
  $linkBegin = strpos("<a href=", linkEnd);
  $linkBegin ==-1 || linkBegin>end)
  break;
  $linkBegin = strpos(">", linkBegin+8);
  $linkEnd = strpos("</a>", linkBegin+1);
  buffer.append($strpos(linkBegin+1, linkEnd)).append(",");
  //linkBegin += 5;
  }
  $result = buffer.to();
  if (result.length()<1)
  return "";
  $result = result.trim();
  return result.substr(0, result.length()-1);
  }
  function getBreadCrumb($pageSource)
  {
  $results = new []{"", ""};
  $buffer = new Buffer();
  $start = strpos("<h1 id=\"breadCrumb\">");
  $start == -1)
  return results;
  //echo(start);
  $end = strpos("</h1>", start);
  $linkBegin = start;
  $linkEnd = linkBegin;
  $links = strpos(start, end);
  //links.
  while(linkEnd<end)
  {
  $linkBegin = strpos("<a href=", linkBegin);
  $linkBegin ==-1 || linkBegin>end)
  break;
  $linkBegin = strpos(">", linkBegin+8);
  $linkEnd = strpos("</a>", linkBegin+1);
  buffer.append($strpos(linkBegin+1, linkEnd)).append(",");
  linkBegin += 5;
  }
  results[1] = buffer.to().trim();
  results[1] = results[1].substr(0, results[1].length()-1);
  $linkBegin = $pageSource.lastIndexOf("</span>", end);
  results[0] =  strpos(linkBegin+7,end).trim();

  return results;
  } */

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

//public static 
/*
  function processBrandPage($pageSource, fromList,  siteUrl)
  {
  $title = getTitle($pageSource);
  $breadCrumb = getBreadCrumb($pageSource);
  $itemCount = getItemCount($pageSource);
  $keywords = getKeywords($pageSource);
  $brandDesc = getDescription($pageSource);

  $brand = new EcsBrand();
  brand.setTitle(title);
  brand.setBrandName(breadCrumb[0]);
  brand.setPath(breadCrumb[1]);
  //brand.setl
  brand.setFromList(fromList);
  brand.setSiteUrl(siteUrl);
  brand.setItemCount(itemCount);
  brand.setKeywords(keywords);
  brand.setBrandDesc(brandDesc);
  //echo((int)(System.currentTimeMillis()/1000));
  brand.setDateAdded((int)(System.currentTimeMillis()/1000));
  DaoUtil.saveBrand(brand);
  return brand;
  //echo(brand.getBrandId());
  }

  function intToShort(val)
  {
  return Short.valueOf(""+val);//new Short(val);
  } */
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
    $goodsDesc = getTechDetail($pageSource);
    $goodsSn = getProductSN($pageSource);
    $dimension = getProductDimensions($pageSource);
    $viewer = getProductViewers($pageSource);
    $providerName = getProducer($pageSource);
    $productWeight = getProductWeight($pageSource);
    $price = getPrice($pageSource);
    $market_price = getProductMarketDate($pageSource);
    $goods_number = getItemCount($pageSource);
    $product = array(
        'id'=>$id,
        'cat_id' => $cat_id, 'goods_sn' => $goodsSn, 'goods_name' => $goodsName, 'goods_name_style' => $dimension,
        'click_count' => $viewer, 'brand_id' => 0, 'provider_name' => $providerName,
        'goods_number' => $goods_number, 'goods_weight' => $productWeight, 'market_price' => $market_price, 'shop_price' => $price,
        'promote_price' => 0, 'keywords' => $keywords, 'goods_desc' => $desc, 'goods_thumb' => $path,
        'goods_img' => '', 'site_url' => '', 'title' => $title, 'path' => $path, 'date_added' => time()
    );
    insert_product($product);
    getTogetherProducts($pageSource, $cat_id);
    getTogetherProducts2($pageSource, $cat_id);
    getTogetherProducts3($pageSource, $cat_id);
    /*
      $goods = new EcsGoods();
      goods.setGoodsName(goodsName);
      goods.setTitle(title);
      //goods.setGoodsName(breadCrumb[0]);
      goods.setPath(path);
      //brand.set
      goods.setCatId(catId);
      goods.setSiteUrl(siteUrl);
      //brand.setItemCount(itemCount);
      goods.setKeywords(keywords);
      goods.setGoodsBrief(desc);


      goods.setGoodsDesc(goodsDesc);
      //echo((int)(System.currentTimeMillis()/1000));
      //echo(addTime);
      goods.setAddTime(addTime);
      goods.setLastUpdate((int)(System.currentTimeMillis()/1000));
      goods.setGoodsSn(goodsSn);
      goods.setGoodsNameStyle(dimension);
      goods.setClickCount(viewer);
      goods.setBrandId(brandId);
      goods.setProviderName(providerName);
      goods.setGoodsWeight(goodsWeight);
      goods.setMarketPrice(marketPrice);
      //DaoUtil.saveGoods(goods); */

    //   $similarProducts = getSimilarProducts($pageSource);
    //   $togetherProducts = getTogetherProducts($pageSource);
    /*
      $bought = new GoodsBought();
      $together = new GoodsTogether();

      bought.setProductId(goods.getGoodsId());
      bought.setStar5(starDetail[0]);
      bought.setStar4(starDetail[1]);
      bought.setStar3(starDetail[2]);
      bought.setStar2(starDetail[3]);
      bought.setStar1(starDetail[4]);

      bought.setPrime(similarProducts[0]);
      bought.setGoods1(similarProducts[1]);
      bought.setGoods2(similarProducts[2]);
      bought.setGoods3(similarProducts[3]);
      bought.setGoods4(similarProducts[4]);
      //	echo(bought.getPrime());
      DaoUtil.saveBought(bought);

      together.setProductId(goods.getGoodsId());
      together.setPrime(togetherProducts[0]);
      together.setGoods1(togetherProducts[1]);
      together.setGoods2(togetherProducts[2]);
      together.setGoods3(togetherProducts[3]);
      together.setGoods4(togetherProducts[4]);
      together.setGoods5(togetherProducts[5]);
      together.setGoods6(togetherProducts[6]);
      together.setGoods7(togetherProducts[7]);
      together.setGoods8(togetherProducts[8]); */
    //	echo("xxxxx\n");
    //	echo(together.getPrime());
    //	DaoUtil.saveTogether(together);
    //	return goods;
    //echo(brand.getBrandId());
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

/*
  function saveHistory()
  {
  $content = getFileContent("urls.txt");
  $lines = content.split("\r\n");
  //line;
  for(line: lines)
  {
  if (!AmazonUtil.skipProduct(line))
  {
  echo(line);
  $crawlHistory = new CrawlHistory();
  crawlHistory.setSiteUrl(line);
  crawlHistory.setCrawlTime(AmazonUtil.getCurrentTime());
  DaoUtil.saveCrawlHistory(crawlHistory);
  }

  }
  }
 */
$sql = "select id, url, cat_id from dm_product where grabbed=0 limit 50";
$rs = my_query($sql) or die(my_error());
while ($row = mysql_fetch_row($rs))
{
    $id = $row[0];
    $url = $row[1];
    $cat_id = $row[2];
    try {
    $web_data = curl($url); 
}catch(Exception $e)
        {
             //echo json_encode($e->getCode()), "<br>\n"; 
            $sql = "update dm_product set grabbed=2 where id=$id ";
            echo "$sql, $url<br>\n";
            my_execute($sql);
            continue;
            log_grab_error($id, $url, $e);
            continue;
        }
//function processProductPage($pageSource, $id, $cat_id)
    processProductPage($web_data, $id , $cat_id);
}
//test();
?>