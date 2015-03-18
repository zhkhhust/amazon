<?php
function after_save_product($data)
{
    $now = time();
    $id = $data['id'];
    $sql = "update dm_product_link set grabbed=1, grab_time=$now where id=$id";
    my_execute($sql);
}
function save_product($data)
{
    save_object(TBL_PRODUCT, $data);
    //after_save_product($data);
}

function save_seller($seller_name, $seller_url)
{
    $seller_name = my_escape($seller_name);
    $sql = "select id from dm_seller where seller_name ='$seller_name'";
    $rs = my_query($sql) or die(mysql_error());
    $row = mysql_fetch_row($rs);
    if ($row)
    {
        return $row[0];
    }
    //if (mysql)
    $sql = "insert into dm_seller(seller_name, seller_url) values('$seller_name', '$seller_url')";
    //echo $sql;
    my_execute($sql);
    return mysql_insert_id();
}
function save_object($table, $data) {
    if (!is_array($data))
    {
       echo "$table <br>\n";
       var_dump($data);
       return;
    }
    $fields = array_keys($data);
    $field_list = implode('`, `', $fields);
    $field_list = '`' . $field_list . '`';
    $data = array_map('mysql_real_escape_string', $data);
    $values = array_values($data);
    $value_list = implode("', '", $values);
    $value_list = "'" . $value_list . "'";
    $sql = "insert into $table($field_list) values($value_list)";
    //echo $sql, "<br>\n";
    my_execute($sql);
}/*
$str = array();
function save_object1($table, $data) {
    global $str;
    $fields = array_keys($data);
    $field_list = implode('` varchar(64), `', $fields);
    $field_list = "create table $table(`" . $field_list . '`';
    if (!isset($str[$table]))
        echo $field_list, ");<br>\n";
    //else
        $str[$table] = 1;
    $data = array_map('mysql_real_escape_string', $data);
    $values = array_values($data);
    $value_list = implode("', '", $values);
    $value_list = "'" . $value_list . "'";
    $sql = "insert into $table($field_list) values($value_list)";
    //echo $sql, "<br>\n";
    //my_execute($sql);
}*/
?>