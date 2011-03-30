<?php
/*  ------------------------------------- *
 *
 *    Projekt:   MySQL Diff
 *    F�r:
 *    Copyright: Lippe-Net Online-Service
 *               Bielefeld, Lemgo
 *               (c) 2001-2003
 *
 *    $Author: sskus $
 *    $RCSfile: resources.lib.php,v $
 *    $Revision: 1.12 $
 *    $Date: 2003/10/10 15:40:48 $
 *    $State: Exp $
 *
 * ------------------------------------- */

function ext_htmlentities($string) {
	GLOBAL $textres_charset, $php_errormsg;

	$dummy = @htmlentities($string, ENT_COMPAT, $textres_charset);
	if ( !isset($php_errormsg) || trim($php_errormsg) == "" ) $string = $dummy;
        return str_replace(array("‹", "›"), array("&#8249;", "&#8250;"), $string);
}

function array_remove_keys($array, $keys = array()) {

    // If array is empty or not an array at all, don't bother
    // doing anything else.
    if(empty($array) || (! is_array($array))) {
        return $array;
    }

    // If $keys is a comma-separated list, convert to an array.
    if(is_string($keys)) {
        $keys = explode(',', $keys);
    }

    // At this point if $keys is not an array, we can't do anything with it.
    if(! is_array($keys)) {
        return $array;
    }

    // array_diff_key() expected an associative array.
    $assocKeys = array();
    foreach($keys as $key) {
        $assocKeys[$key] = true;
    }

    return array_diff_key($array, $assocKeys);
}

function fixMysqlString($string, &$link) {
    if (version_compare(phpversion(), "4.3.0", ">=")):
        return mysql_real_escape_string($string, $link);
    else:
        return mysql_real_escape($string);
    endif;
}

?>
