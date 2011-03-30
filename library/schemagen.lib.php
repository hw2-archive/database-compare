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
 *    $RCSfile: generator.lib.php,v $
 *    $Revision: 1.47 $
 *    $Date: 2003/10/12 21:53:51 $
 *    $State: Exp $
 *
 * ------------------------------------- */

$syntaxhighlight = FALSE;

$translates = array(
    "signs" => array(
        "translate" => "<span class=\"signs\">\\1</span>",
        "items" => array(
            "/([\.,\(\)-])/im"
        )
    ),
    "num" => array(
        "translate" => "\\1<span class=\"num\">\\2</span>\\3",
        "items" => array(
            "/(\b)(\d+)(\b)/im"
        )
    )
);

define("HIGHLIGHT_DML", "dml");
define("HIGHLIGHT_DDL", "ddl");
define("HIGHLIGHT_TYPE", "type");
define("HIGHLIGHT_CONSTANTS", "const");
define("HIGHLIGHT_VALUES", "values");
define("HIGHLIGHT_NUMBER", "num");
define("HIGHLIGHT_SIGNS", "signs");
define("HIGHLIGHT_OBJECT", "obj");

define("CONSTRAINT_ADD", 0);
define("CONSTRAINT_DROP", 1);

$source_server = $target_server = "";

function fetchServerVersion(&$lnk, $format = "%02d%02d%04d") {
    $temp = mysql_get_server_info($lnk);
    preg_match("/^(\d+)\.(\d+)\.(\d+)/", $temp, $matches);
    return sprintf($format, $matches[1], $matches[2], $matches[3]);
}

function highlightString($what, $kind = HIGHLIGHT_DDL) {
    GLOBAL $syntaxhighlight;

    if ($syntaxhighlight) {
        return "<span class=\"$kind\">$what</span>";
    } else
        return $what;
}

function typeString($type) {
    GLOBAL $syntaxhighlight;

    if ($syntaxhighlight)
        $type = preg_replace("/([(])(\\d+)([)])/", "<span class=\"signs\">$1</span><span class=\"num\">$2</span><span class=\"signs\">$3</span>", $type);
    return highlightString($type, "type");
}

function translate($item) {
    GLOBAL $translates, $syntaxhighlight;

    if ($syntaxhighlight)
        foreach ($translates AS $types) {
            foreach ($types["items"] AS $items) {
                $item = preg_replace($items, $types["translate"], $item);
            }
        }
    return str_replace("  ", "&nbsp;&nbsp;", $item);
}

function alternateNullDefault($type) {
    if (strtolower(substr($type, 0, 4)) == "int(" || strtolower(substr($type, 0, 8)) == "bigint(" || strtolower(substr($type, 0, 8)) == "smallint(" || strtolower(substr($type, 0, 8)) == "tinyint(" || strtolower(substr($type, 0, 10)) == "mediumint(") {
        $result = "0";
    } else if (strtolower(substr($type, 0, 8)) == "datetime") {
        $result = "0000-00-00 00:00:00";
    } else if (strtolower(substr($type, 0, 4)) == "date") {
        $result = "0000-00-00";
    } else if (strtolower(substr($type, 0, 4)) == "time") {
        $result = "00:00:00";
    } else {
        $result = '';
    }
    return $result;
}

function fieldString($field, $withname=TRUE) {
    return ( $withname ? objectName($field["name"]) . " " : "" ) . typeString($field["type"]) . ( $field["null"] ? " " . highlightString("NULL", HIGHLIGHT_CONSTANTS) : " " . highlightString("NOT NULL", HIGHLIGHT_CONSTANTS) ) . " " . ( isset($field["extra"]) && $field["extra"] != "" ? $field["extra"] : ( highlightString("DEFAULT", HIGHLIGHT_DDL) . " " . ( isset($field["default"]) ? highlightstring("'" . $field["default"] . "'", HIGHLIGHT_VALUES) : ( $field["null"] ? highlightString("NULL", HIGHLIGHT_CONSTANTS) : alternateNullDefault($field["type"]) ) ) ) );
}

function indexString($idx) {
    $result = ( $idx["unique"] ? ( $idx["name"] == "PRIMARY" ? highlightString("PRIMARY KEY") : highlightString("UNIQUE") . " " . objectName($idx["name"]) ) : highlightString("INDEX") . " " . objectName($idx["name"]) ) . " (";
    $i = 1;
    $im = count($idx["fields"]);
    foreach ($idx["fields"] AS $vf) {
        $result.=objectName($vf["name"]) . ( isset($vf["sub"]) ? "(" . $vf["sub"] . ")" : "" ) . ( $i < $im ? ", " : "" );
        $i++;
    }
    $result.=")";
    return $result;
}

function constraintString($idx, $targetdb, $what = CONSTRAINT_ADD, $serverversion = NULL) {
    if ($what == CONSTRAINT_ADD) {
        $result = highlightString("ADD CONSTRAINT") . " " . highlightString($idx["type"]) . translate(" (") . $idx["name"] . translate(") ") . highlightString("REFERENCES") . " " . objectName(( $targetdb != $idx["targetdb"] ? $idx["targetdb"] . "." : "" ) . $idx["targettable"]) . translate(" (") . $idx["targetcols"] . translate(")") . ( isset($idx["params"]) && trim($idx["params"]) != "" ? highlightString($idx["params"]) : "" );
    } else if ($what == CONSTRAINT_DROP && isset($serverversion) && $serverversion >= 4000013) {
        $result = highlightString("DROP " . $idx["type"]) . " " . $idx["id"];
    } else
        $result = "";
    return $result;
}

function indexOn($idx, $tableA="a", $tableB="b") {
    $fields = "";
    if (isset($idx["fields"]) && is_array($idx["fields"]))
        foreach ($idx["fields"] AS $key => $value) {
            $fields.= ( $fields == "" ? "" : " AND " ) . "$tableA.$key=$tableB.$key";
        }
    return $fields;
}

function indexNull($idx, $table="b") {
    $fields = "";
    if (isset($idx["fields"]) && is_array($idx["fields"]))
        foreach ($idx["fields"] AS $key => $value) {
            $fields.= ( $fields == "" ? "" : " AND " ) . "$table.$key IS NULL";
        }
    return $fields;
}

function fetchIndexes($table, $id) {
    $result = NULL;
    if ($res = mysql_query("SHOW INDEX FROM `$table`", $id)) {
        while ($row = mysql_fetch_array($res)) {
            $result[$row["Key_name"]]["name"] = $row["Key_name"];
            $result[$row["Key_name"]]["unique"] = $row["Non_unique"] == 0 ? 1 : 0;
            $result[$row["Key_name"]]["fields"][$row["Column_name"]]["name"] = $row["Column_name"];
            if (isset($row["Sub_part"]) && $row["Sub_part"] != "")
                $result[$row["Key_name"]]["fields"][$row["Column_name"]]["sub"] = $row["Sub_part"];
        }
        mysql_free_result($res);
    }
    return isset($result) ? $result : NULL;
}

function fetchFields($table, $id) {
    $result = NULL;
    if ($res = mysql_query("SHOW FIELDS FROM `$table`", $id)) {
        while ($row = mysql_fetch_array($res)) {
            $result[$row["Field"]] = array("name" => $row["Field"], "type" => $row["Type"], "null" => ( isset($row["Null"]) && $row["Null"] == "YES" ? 1 : 0 ), "default" => ( isset($row["Default"]) ? $row["Default"] : NULL ), "extra" => ( isset($row["Extra"]) ? $row["Extra"] : NULL ));
        }
        mysql_free_result($res);
    }
    return isset($result) ? $result : NULL;
}

function fetchTables($db, $id, $sel_tab) {
    $result = NULL;
    @mysql_select_db($db, $id);
    if ($res = mysql_query("SHOW TABLE STATUS FROM `$db`", $id)) {
        while ($row = mysql_fetch_array($res)) {
            if (in_array($row[0], $sel_tab)) {
                $indexes = fetchIndexes($row[0], $id);
                $fields = fetchFields($row[0], $id);
                $constraints = array();
                if ($row["Type"] == "InnoDB") {
                    $cparts = explode("; ", $row["Comment"]);
                    $comment = preg_match("/^InnoDB free:/i", $c = trim($cparts[0])) ? "" : $c;

                    if ($tabres = mysql_query("SHOW CREATE TABLE `" . $row["Name"] . "`", $id)) {
                        $obj = mysql_fetch_array($tabres, MYSQL_ASSOC);
                        if (preg_match_all("/(CONSTRAINT `([0-9_]+)` )?(FOREIGN KEY) \(([^)]+)\) REFERENCES `(([A-Z0-9_$]+)(\.([A-Z0-9_$]+))?)` \(([^)]+)\)( ON (DELETE|UPDATE)( (CASCADE|SET NULL|NO ACTION|RESTRICT)))?/i", $obj["Create Table"], $matches, PREG_SET_ORDER)) {
                            foreach ($matches AS $match) {
                                $constraints[$match[4]] = array(
                                    "name" => $match[4],
                                    "id" => $match[2],
                                    "type" => $match[3],
                                    "targetdb" => isset($match[8]) && trim($match[8]) != "" ? $match[6] : $db,
                                    "targettable" => isset($match[8]) && trim($match[8]) != "" ? $match[8] : $match[6],
                                    "targetcols" => $match[9],
                                    "params" => isset($match[10]) ? $match[10] : NULL,
                                );
                            }
                        }
                        mysql_free_result($tabres);
                    }
                } else {
                    $comment = trim($row["Comment"]);
                }
                $result[$row["Name"]] = array("name" => $row["Name"], "type" => $row["Type"], "options" => $row["Create_options"], "comment" => $comment, "fields" => $fields, "idx" => $indexes, "constraints" => $constraints);
            }
        }
        mysql_free_result($res);
    }
    return isset($result) ? $result : NULL;
}

function fieldsDiff($f1, $f2) {
    if (count($f1) != count($f2))
        return TRUE;
    foreach ($f1 AS $key => $value) {
        if (!isset($f2[$key]) || $value["name"] != $f2[$key]["name"])
            return TRUE;
    }
    return FALSE;
}

function objectName($name) {
    $reservedwords = array(
        "ADD", "ACTION", "ALL", "ALTER", "ANALYZE", "AND", "AS", "ASC", "ASENSITIVE", "AUTO_INCREMENT",
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

    return highlightString($_SESSION["options"]["backticks"] || preg_match("/[^a-z0-9]/i", $name) || in_array(strtoupper($name), $reservedwords) ? "`" . $name . "`" : $name, HIGHLIGHT_OBJECT);
}

function generateScript($sel_tables, $targetdb, $sourcedb, $syntax = FALSE, $html = TRUE) {
    GLOBAL $syntaxhighlight, $source_server, $target_server;

    $s_id = $sourcedb->getMysqlConnection();
    $t_id = $targetdb->getMysqlConnection();

    $syntaxhighlight = $syntax;
    $result_string = "";

    if ($s_id) {
        $source_server = fetchServerVersion($s_id);
        if (@mysql_select_db($sourcedb->name, $s_id)) {
            if ($t_id) {
                $target_server = fetchServerVersion($t_id);
                if (@mysql_select_db($targetdb->name, $t_id)) {
                    $s_tab = fetchTables($sourcedb->name, $s_id, $sel_tables);
                    $t_tab = fetchTables($targetdb->name, $t_id, $sel_tables);

                    if (is_array($t_tab))
                        foreach ($t_tab AS $key => $value) {
                            if (!isset($s_tab[$key])) {
                                $item = highlightString("CREATE TABLE") . " " . objectName($key) . " " . translate("(") . "\n";
                                $idx = 1;
                                $max = count($value["fields"]);
                                foreach ($value["fields"] AS $vf) {
                                    $item.="    " . fieldString($vf) . ( $idx < $max || count($value["idx"]) ? translate(",") : "" ) . "\n";
                                    $idx++;
                                }
                                $idx = 1;
                                $max = count($value["idx"]);
                                if (isset($value["idx"]))
                                    foreach ($value["idx"] AS $vx) {
                                        $item.="    " . indexString($vx) . ( $idx < $max ? "," : "" ) . "\n";
                                        $idx++;
                                    }
                                $item.=translate(")");
                                if ($_SESSION["options"]["type"]) {
                                    if (isset($t_tab[$key]["type"]) && $t_tab[$key]["type"] != "") {
                                        $item.=" " . highlightString("TYPE") . highlightstring("=", HIGHLIGHT_SIGNS) . highlightstring($t_tab[$key]["type"], HIGHLIGHT_CONSTANTS);
                                    }
                                }
                                if ($_SESSION["options"]["options"]) {
                                    if (isset($t_tab[$key]["options"]) && $t_tab[$key]["options"] != "") {
                                        $item.=" " . $t_tab[$key]["options"];
                                    }
                                }
                                if ($_SESSION["options"]["comment"]) {
                                    if (isset($t_tab[$key]["comment"]) && $t_tab[$key]["comment"] != "") {
                                        $item.=" " . highlightString("COMMENT") . highlightstring("=", HIGHLIGHT_SIGNS) . "'" . ( function_exists("mysql_escape_string") ? mysql_escape_string($t_tab[$key]["comment"]) : addslashes($t_tab[$key]["comment"]) ) . translate("'");
                                    }
                                }
                                $item.=translate(";") . "\n\n";
                                $result_string .= $item;
                            }
                        }
                    $added_fields = array();
                    if (is_array($s_tab))
                        foreach ($s_tab AS $key => $value) {
                            if (isset($t_tab[$key])) {
                                $altered = 0;
                                $altering = "";
                                $alteredfields = NULL;

                                $lastfield = NULL;

                                $added_fields[$key] = array();
                                foreach ($t_tab[$key]["fields"] AS $vk => $vf) {
                                    if (!isset($s_tab[$key]["fields"][$vk])) {
                                        if (isset($_SESSION["renamed"][$key]) && in_array($vk, $_SESSION["renamed"][$key])) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : ",\n") . "    " . highlightString("CHANGE") . " " . objectName(array_search($vk, $_SESSION["renamed"][$key])) . " " . fieldString($t_tab[$key]["fields"][$vk]);
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("CHANGE") . " " . objectName($vk) . " " . fieldString($t_tab[$key]["fields"][$vk]) . ";\n";
                                        } else {
                                            $added_fields[$key][] = $vk;
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("ADD") . " " . fieldString($t_tab[$key]["fields"][$vk]) . ( isset($lastfield) ? " " . highlightString("AFTER") . " $lastfield" : " " . highlightString("FIRST") );
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("ADD") . " " . fieldString($t_tab[$key]["fields"][$vk]) . ( isset($lastfield) ? " " . highlightString("AFTER") . " $lastfield" : " " . highlightString("FIRST") ) . translate(";") . "\n";
                                        }
                                        $altered++;
                                    }
                                    $lastfield = $t_tab[$key]["fields"][$vk]["name"];
                                }

                                foreach ($value["fields"] AS $vk => $vf) {
                                    if (isset($t_tab[$key]["fields"][$vk])) {
                                        if ($vf["type"] == $t_tab[$key]["fields"][$vk]["type"] && $vf["null"] == $t_tab[$key]["fields"][$vk]["null"] && $vf["default"] != $t_tab[$key]["fields"][$vk]["default"]) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : ",\n") . "    " . highlightString("ALTER") . " " . objectName($t_tab[$key]["fields"][$vk]["name"]) . " " . ( isset($t_tab[$key]["fields"][$vk]["default"]) ? highlightString("SET DEFAULT") . " " . ( is_numeric($t_tab[$key]["fields"][$vk]["default"]) ? $t_tab[$key]["fields"][$vk]["default"] : "'" . $t_tab[$key]["fields"][$vk]["default"] . "'" ) : " " . highlightString("DROP DEFAULT") );
                                                $alterfields[] = array("name" => $key . "." . $t_tab[$key]["fields"][$vk]["name"], "from" => fieldString($s_tab[$key]["fields"][$vk], FALSE), "to" => fieldString($t_tab[$key]["fields"][$vk], FALSE));
                                            } else {
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("ALTER") . " " . objectName($t_tab[$key]["fields"][$vk]["name"]) . " " . ( isset($t_tab[$key]["fields"][$vk]["default"]) ? highlightString("SET DEFAULT") . " " . ( is_numeric($t_tab[$key]["fields"][$vk]["default"]) ? $t_tab[$key]["fields"][$vk]["default"] : "'" . $t_tab[$key]["fields"][$vk]["default"] . "'" ) : " " . highlight("DROP DEFAULT") );
                                                $result_string .= "#\n#  Fieldformat of '$key.$vk' changed from '" . fieldString($s_tab[$key]["fields"][$vk], FALSE) . " to " . fieldString($t_tab[$key]["fields"][$vk], FALSE) . ". Possibly data modifications needed!\n#\n\n";
                                            }
                                        } else if ($vf["type"] != $t_tab[$key]["fields"][$vk]["type"] || $vf["null"] != $t_tab[$key]["fields"][$vk]["null"] || $vf["default"] != $t_tab[$key]["fields"][$vk]["default"]) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : ",\n") . "    " . highlightString("MODIFY") . " " . fieldString($t_tab[$key]["fields"][$vk]);
                                                $alteredfields[] = array("name" => $key . "." . $t_tab[$key]["fields"][$vk]["name"], "from" => fieldString($s_tab[$key]["fields"][$vk], FALSE), "to" => fieldString($t_tab[$key]["fields"][$vk], FALSE));
                                            } else {
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("MODIFY") . " " . fieldString($t_tab[$key]["fields"][$vk]) . ";\n";
                                                $result_string .= "#\n#  Fieldformat of '$key.$vk' changed from '" . fieldString($s_tab[$key]["fields"][$vk], FALSE) . " to " . fieldString($t_tab[$key]["fields"][$vk], FALSE) . ". Possibly data modifications needed!\n#\n\n";
                                            }
                                            $altered++;
                                        }
                                    } else {
                                        if (!isset($_SESSION["renamed"][$key][$vk])) {
                                            $addedfieldnames = "";
                                            foreach ($added_fields[$key] AS $addfld) {
                                                $addedfieldnames .= ( $addedfieldnames == "" ? "" : "&" ) . "fields[]=" . urlencode($addfld);
                                            }
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("DROP") . " " . objectName($vk);
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("DROP") . " " . objectName($vk) . translate(";") . "\n";
                                        }
                                        $altered++;
                                    }
                                }

                                if (isset($t_tab[$key]["idx"]))
                                    foreach ($t_tab[$key]["idx"] AS $vk => $vf) {
                                        if (!isset($s_tab[$key]["idx"][$vk])) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("ADD") . " " . indexString($vf);
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("ADD") . " " . indexString($vf) . translate(";") . "\n";
                                            $altered++;
                                        }
                                    }

                                if (isset($value["idx"]))
                                    foreach ($value["idx"] AS $vk => $vf) {
                                        if (isset($t_tab[$key]["idx"][$vk])) {
                                            if (fieldsdiff($vf["fields"], $t_tab[$key]["idx"][$vk]["fields"])) {
                                                if ($_SESSION["options"]["comment"]) {
                                                    $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("DROP") . " " . ( $vf["unique"] && $vk == "PRIMARY" ? highlightString("PRIMARY KEY") : highlightString("INDEX") . " $vk" ) . translate(",") . "\n    " . highlightString("ADD") . " " . indexString($t_tab[$key]["idx"][$vk]);
                                                } else
                                                    $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("DROP") . " " . ( $vf["unique"] && $vk == "PRIMARY" ? highlightString("PRIMARY KEY") : highlightString("INDEX") . " $vk" ) . translate(";\n") . highlightString("ALTER TABLE") . " $key " . highlightString("ADD") . " " . indexString($t_tab[$key]["idx"][$vk]) . translate(";") . "\n";
                                            }
                                        } else {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("DROP") . " " . ( $vf["unique"] && $vk == "PRIMARY" ? highlightString("PRIMARY KEY") : highlightString("INDEX") . " $vk" );
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("DROP") . " " . ( $vf["unique"] && $vk == "PRIMARY" ? highlightString("PRIMARY KEY") : highlightString("INDEX") . " $vk" ) . translate(";") . "\n";
                                            $altered++;
                                        }
                                    }

                                // Constraints
                                if (isset($s_tab[$key]["constraints"]))
                                    foreach ($s_tab[$key]["constraints"] AS $vk => $vf) {
                                        if (!isset($t_tab[$key]["constraints"][$vk])) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . constraintString($vf, $targetdb->name, CONSTRAINT_DROP, $target_server);
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . constraintString($vf, $targetdb->name, CONSTRAINT_DROP, $target_server) . translate(";") . "\n";
                                            $altered++;
                                        }
                                    }
                                if (isset($t_tab[$key]["constraints"]))
                                    foreach ($t_tab[$key]["constraints"] AS $vk => $vf) {
                                        if (!isset($s_tab[$key]["constraints"][$vk])) {
                                            if ($_SESSION["options"]["comment"]) {
                                                $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . constraintString($vf, $targetdb->name, CONSTRAINT_ADD);
                                            } else
                                                $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . constraintString($vf, $targetdb->name, CONSTRAINT_ADD) . translate(";") . "\n";
                                            $altered++;
                                        }
                                    }

                                // Tabellenoptionen
                                if ($_SESSION["options"]["type"]) {
                                    if ($value["type"] != $t_tab[$key]["type"]) {
                                        if ($_SESSION["options"]["comment"]) {
                                            $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("TYPE") . highlightstring("=", HIGHLIGHT_SIGNS) . highlightstring($t_tab[$key]["type"], HIGHLIGHT_CONSTANTS);
                                        } else
                                            $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlighString("TYPE") . highlightstring("=", HIGHLIGHT_SIGNS) . highlightstring($t_tab[$key]["type"], HIGHLIGHT_CONSTANTS) . translate(";") . "\n";
                                        $altered++;
                                    }
                                }

                                if ($_SESSION["options"]["options"]) {
                                    if ($value["options"] != $t_tab[$key]["options"]) {
                                        if ($_SESSION["options"]["comment"]) {
                                            $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . $t_tab[$key]["options"];
                                        } else
                                            $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString($t_tab[$key]["options"], HIGHLIGHT_VALUES) . translate(";") . "\n";
                                        $altered++;
                                    }
                                }

                                if ($_SESSION["options"]["comment"]) {
                                    if ($value["comment"] != $t_tab[$key]["comment"]) {
                                        if ($_SESSION["options"]["comment"]) {
                                            $altering.= ( $altering == "" ? "" : translate(",") . "\n") . "    " . highlightString("COMMENT") . highlightstring("=", HIGHLIGHT_SIGNS) . "'" . ( function_exists("mysql_escape_string") ? mysql_escape_string($t_tab[$key]["comment"]) : addslashes($t_tab[$key]["comment"]) ) . translate("'");
                                        } else
                                            $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . " " . highlightString("COMMENT") . highlightstring("=", HIGHLIGHT_SIGNS) . "'" . ( function_exists("mysql_escape_string") ? mysql_escape_string($t_tab[$key]["comment"]) : addslashes($t_tab[$key]["comment"]) ) . translate("';") . "\n";
                                        $altered++;
                                    }
                                }

                                // Abschluss ...
                                if ($altering != "") {
                                    $result_string .= highlightString("ALTER TABLE") . " " . objectName($key) . "\n$altering;\n";
                                    if (isset($alteredfields)) {
                                        $result_string .= "#\n";
                                        $result_string .= "#  " . $textres["info_fieldformat_changed" . ( count($alteredfields) == 1 ? "_single" : "_multiple" )] . "\n";
                                        foreach ($alteredfields AS $val) {
                                            $result_string .= "#    " . sprintf($textres["info_fieldformat_changeinfo"], $val["name"], $val["from"], $val["to"]) . "\n";
                                        }
                                        $result_string .= "#  " . $textres["info_fieldformat_modification_needed"] . "\n";
                                        $result_string .= "#\n";
                                    } $result_string .= "\n";
                                } else if ($altered)
                                    $result_string .= "\n";
                            } else {
                                $result_string .= highlightString("DROP TABLE") . " " . objectName($key) . translate(";") . "\n\n";
                            }
                        }
                } else
                    die("error1");
                @mysql_close($t_id);
            } else
                die("error2");
        } else
            die("error3");
        @mysql_close($s_id);
    } else
        die("error4");

    return $result_string;
}

?>