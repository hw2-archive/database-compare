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
 *    $RCSfile: global.inc.php,v $
 *    $Revision: 1.9 $
 *    $Date: 2003/10/23 15:58:29 $
 *    $State: Exp $
 *
 * ------------------------------------- */

include("values.lib.php");
include 'progressbar.class.php';
include 'updatefile.class.php';
include 'resources.lib.php';
include 'schemagen.lib.php';

define("VERSION", "1.0.0");
define("REQUIRED_PHP_VERSION", "4.1.0");
ini_set("session.use_cookies", 0);
session_name( "sID" );

?>