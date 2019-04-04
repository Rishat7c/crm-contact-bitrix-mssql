<?php
/**
 * Created by PhpStorm.
 * User: Gaysin.R
 * Date: 04.04.2019
 * Time: 12:16
 */

use \Bitrix\Main;

$_SERVER["DOCUMENT_ROOT"] = $DOCUMENT_ROOT = realpath(__DIR__ . '/../../../');

set_time_limit(0);

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/oracleIntegrationClass.php");

CrmContactController::updateContacts();