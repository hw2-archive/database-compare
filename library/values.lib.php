<?php
/*  ------------------------------------- *
 *
 *    Projekt:   MySQL Diff
 *    Für:
 *    Copyright: Lippe-Net Online-Service
 *               Bielefeld, Lemgo
 *               (c) 2001-2003
 *
 *    $Author: sskus $
 *    $RCSfile: values.lib.php,v $
 *    $Revision: 1.3 $
 *    $Date: 2003/09/26 11:13:06 $
 *    $State: Exp $
 *
 * ------------------------------------- */

function createOptionsFromArray(&$values, $default = NULL, $keyvalue = TRUE) {
	$result="";
	if ( isset($values) && is_array($values) ) foreach ( $values AS $key=>$value ) {
		$result.="<option value=\"".( $keyvalue ? $key : $value )."\"".( isset($default) && ( ( is_array($default) && in_array($keyvalue ? $key : $value, $default) ) || ( !is_array($default) && ( $keyvalue ? $key : $value ) == $default ) ) ? " selected=\"selected\"" : "" ).">$value</option>";
	}
	return $result;
}

function createHostsOptions($values, $default) {
	$items = array();
	foreach ( $values AS $key => $value ) {
		$items[$key] = $value["hostname"];
	}
	return createOptionsFromArray($items, isset($default) ? $default : NULL);
}

function createDatabaseOptions($default, $host, $user, $pass) {
	$result = "";
	if ( $con = @mysql_connect($host, $user, $pass) ) {
		if ( $res = mysql_list_dbs($con) ) {
			$items = array();
			while ( $row = mysql_fetch_object($res) ) {
				$items[$row->Database] = $row->Database;
			}
			$result = createOptionsFromArray($items, isset($default) ? $default : NULL);
			mysql_free_result($res);
		}
		mysql_close($con);
	}
	return $result;
}

function createDatabaseControl($ctrlname, $default, $host, $user, $pass) {
	$error = FALSE;
	if ( ( $options = createDatabaseOptions(isset($default) ? $default : NULL, $host, $user, $pass) ) == "" ) {
		$result = "<input id=\"txt$ctrlname\" name=\"txt$ctrlname\" class=\"login\" type=\"text\" maxlength=\"100\" size=\"30\"".( isset($default) ? " value=\"$default\"" : "" )." />";
	} else $result = "<select id=\"txt$ctrlname\" name=\"sel$ctrlname\" class=\"login\" size=\"1\">$options</select>";
	return $result;
}

?>