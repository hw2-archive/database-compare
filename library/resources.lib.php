<?php
/*  ------------------------------------- *
 *
 *    Projekt:   MySQL Diff
 *    For:
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

function fixTableName($name,$backticks = false, $addFilters = false) {
    return fixObjectName($name, $backticks, $addFilters);
}

function fixFieldName($name,$backticks = false, $addFilters = false) {
    return fixObjectName($name, $backticks, $addFilters);
}

function fixObjectName($name,$backticks = false,$addFilters = false) {

    $reservedwords = array(
        "ACCESS" , "ADD", "ACTION", "ALL", "ALTER", "ANALYZE", "AND", "AS", "ASC", "ASENSITIVE", "AUTO_INCREMENT",
        "BDB", "BEFORE", "BERKELEYDB", "BETWEEN", "BIGINT", "BINARY", "BIT", "BLOB", "BOTH", "BTREE", "BY",
        "CALL", "CASCADE", "CASE", "CHANGE", "CHAR", "CHARACTER", "CHECK", "COLLATE", "COLUMN", "COLUMNS", "CONNECTION", "CONSTRAINT", "CREATE", "CROSS", "CURRENT_DATE", "CURRENT_TIME", "CURRENT_TIMESTAMP", "CURSOR",
        "DATE", "DATABASE", "DATABASES", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "DEC", "DECIMAL", "DECLARE", "DEFAULT", "DELAYED", "DELETE", "DESC", "DESCRIBE", "DISTINCT", "DISTINCTROW", "DIV", "DOUBLE", "DROP",
        "ENUM", "ELSE", "ELSEIF", "ENCLOSED", "ERRORS", "ESCAPED", "EXISTS", "EXPLAIN",
        "FALSE", "FIELDS", "FLOAT", "FOR", "FORCE", "FOREIGN", "FROM", "FULLTEXT",
        "GRANT", "GROUP",
        "HASH", "HAVING", "HIGH_PRIORITY", "HOUR_MINUTE", "HOUR_SECOND",
        "IF", "IGNORE", "IN", "INDEX", "INFILE", "INNER", "INNODB", "INOUT", "INSENSITIVE", "INSERT", "INT", "INTEGER", "INTERVAL", "INTO", "IS", "ITERATE",
        "JOIN",
        "KEY", "KEYS", "KILL",
        "LEADING", "LEAVE", "LEFT", "LIKE", "LIMIT", "LINES", "LOAD", "LOCALTIME", "LOCALTIMESTAMP", "LOCK", "LONG", "LONGBLOB", "LONGTEXT", "LOOP", "LOW_PRIORITY",
        "MASTER_SERVER_ID", "MATCH", "MEDIUMBLOB", "MEDIUMINT", "MEDIUMTEXT", "MIDDLEINT", "MINUTE_SECOND", "MOD", "MRG_MYISAM",
        "NATURAL", "NO", "NOT", "NULL", "NUMERIC",
        "ON", "OPTIMIZE", "OPTION", "OPTIONALLY", "OR", "ORDER", "OUT", "OUTER", "OUTFILE",
        "PRECISION", "PRIMARY", "PRIVILEGES", "PROCEDURE", "PURGE",
        "READ", "REAL", "REFERENCES", "REGEXP", "RENAME", "REPEAT", "REPLACE", "REQUIRE", "RESTRICT", "RETURN", "RETURNS", "REVOKE", "RIGHT", "RLIKE", "RTREE",
        "SELECT", "SENSITIVE", "SEPARATOR", "SET", "SHOW", "SMALLINT", "SOME", "SONAME", "SPATIAL", "SPECIFIC", "SQL_BIG_RESULT", "SQL_CALC_FOUND_ROWS", "SQL_SMALL_RESULT", "SSL", "STARTING", "STRAIGHT_JOIN", "STRIPED",
        "TABLE", "TABLES", "TERMINATED", "TEXT", "THEN", "TIME", "TIMESTAMP", "TINYBLOB", "TINYINT", "TINYTEXT", "TO", "TRAILING", "TRUE", "TYPES",
        "UNION", "UNIQUE", "UNLOCK", "UNSIGNED", "UNTIL", "UPDATE", "USAGE", "USE", "USER_RESOURCES", "USING",
        "VALUES", "VARBINARY", "VARCHAR", "VARCHARACTER", "VARYING",
        "WARNINGS", "WHEN", "WHERE", "WHILE", "WITH", "WRITE",
        "XOR",
        "YEAR_MONTH",
        "ZEROFILL"
    );

    return ($backticks && !$addFilters || $backticks && ($addFilters && ( preg_match("/[^a-z0-9]/i", $name) || in_array(strtoupper($name), $reservedwords))) ? "`" . $name . "`" : $name);
}

?>
