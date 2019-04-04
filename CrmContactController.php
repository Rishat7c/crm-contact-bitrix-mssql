<?php
/**
 * Created by PhpStorm.
 * User: Gaysin.R
 * Date: 04.04.2019
 * Time: 12:15
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class CrmContactController
{

    public static function query($qry = false)
    {

        if (empty($qry))
            return false;

        $db = "(DESCRIPTION=
             (ADDRESS_LIST=
               (ADDRESS=(PROTOCOL=TCP)
                 (HOST=192.10.200.16)(PORT=1441)
               )
             )
               (CONNECT_DATA=(SID=cmbrep))
         )";

        $conn = oci_connect('BD', 'BRydjkfsED', $db);
        $stid = oci_parse($conn, $qry);
        oci_execute($stid);

        while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $arr[] = $row;
        }

        return $arr;
    }


    public static function getContacts()
    {

        Loader::includeModule("crm");

        $limit = Option::get("crm", "oracle_integration_limit");

        if (empty($limit)) {
            $limit = 0;
        }

        $sql = "
            SELECT
                G.ID, G.CUSTOMER_ID, G.ACCOUNT_NAME,
                G.ACCOUNT_NUMBER, G.EMAIL, G.PHONE,
                G.DATE_OF_BIRTH, G.ADDRESS, G.ACCOUNT_BALANCE,
                G.BVNNO, G.BANK_ID
            FROM
                SYSTEM.GROUP_CUST_INFO G
            WHERE G.ID > {$limit}
            ORDER BY G.ID ASC
        ";

        $data = self::query($sql);

        if (empty($data)) {
            \Bitrix\Main\Diag\Debug::dumpToFile(array('ERROR' => 'NO NEW ENTITIES', 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegration.txt");
            return false;
        }

        $ob = new \CCrmContact(false);

        foreach ($data as $item) {

            $checkDuplicate = $ob->GetList([], ['=UF_CRM_1544075307' => $item['CUSTOMER_ID'], 'CHECK_PERMISSIONS' => 'N'], ['ID'])
                ->Fetch();

            if (!empty($checkDuplicate['ID'])) {
                \Bitrix\Main\Diag\Debug::dumpToFile(array('ERROR' => 'DUPLICATE', 'fieldsFromOracle' => $item, 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegration.txt");
                continue;
            }

            if (!empty($item['DATE_OF_BIRTH'])) {
                $birthDate = ConvertTimeStamp(strtotime($item['DATE_OF_BIRTH']));
            }

            $arAdd = [
                'NAME' => $item['ACCOUNT_NAME'],
                'UF_CRM_1543068928780' => $item['ACCOUNT_NAME'],
                'UF_CRM_1544075307' => $item['CUSTOMER_ID'],
                'UF_CRM_1543069029582' => $item['ACCOUNT_NUMBER'],
                'UF_CRM_1543069383842' => $item['ACCOUNT_BALANCE'],
                'UF_CRM_1543069260199' => $item['BVNNO'],
                'UF_CRM_1544158581079' => $item['BANK_ID'],
                'ADDRESS' => $item['ADDRESS'],
                'BIRTHDATE' => $birthDate,
                'UF_CRM_1545214346' => $item['ID'],
                "ASSIGNED_BY_ID" => "1",
                'FM' => array(
                    'EMAIL' => array(
                        'n0' => array('VALUE' => $item['EMAIL'], 'VALUE_TYPE' => 'WORK')
                    ),
                    'PHONE' => array(
                        'n0' => array('VALUE' => $item['PHONE'], 'VALUE_TYPE' => 'WORK')
                    )
                ),
            ];

            $ID = $ob->Add($arAdd);
            if (empty($ID)) {
                \Bitrix\Main\Diag\Debug::dumpToFile(array('ERROR' => $ob->LAST_ERROR, 'fields' => $arAdd, 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegration.txt");
            } else {
                Option::set("crm", "oracle_integration_limit", $item['ID']);
                \Bitrix\Main\Diag\Debug::dumpToFile(array('ID' => $ID, 'fields' => $arAdd, 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegration.txt");
            }
        }


    }

    public static function updateContacts()
    {

        Loader::includeModule("crm");


        $sql = "
            SELECT
                G.CUSTOMER_ID, G.ACCOUNT_BALANCE
            FROM
                SYSTEM.GROUP_CUST_INFO G
        ";

        $data = self::query($sql);

        if (empty($data)) {
            \Bitrix\Main\Diag\Debug::dumpToFile(array('ERROR' => 'NO NEW ENTITIES', 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegrationUpdate.txt");
            return false;
        }

        $ob = new \CCrmContact(false);

        foreach ($data as $item) {

            if (empty($item['CUSTOMER_ID'])) {
                continue;
            }

            $findContact = $ob->GetList([], [
                '=UF_CRM_1544075307' => $item['CUSTOMER_ID'],
                'CHECK_PERMISSIONS' => 'N',
                '!UF_CRM_1543069383842' => $item['ACCOUNT_BALANCE']
            ], ['ID','UF_CRM_1543069383842'])
                ->Fetch();

            if (empty($findContact['ID'])) {
                continue;
            }

            $arUpd = [
                'UF_CRM_1543069383842' => $item['ACCOUNT_BALANCE'],
            ];

            if ($ob->Update($findContact['ID'], $arUpd)) {

                try{

                    if(empty($findContact['UF_CRM_1543069383842'])){
                        $findContact['UF_CRM_1543069383842'] = '';
                    }

                    $comment = "Account Balance changed from {$findContact['UF_CRM_1543069383842']} to {$item['ACCOUNT_BALANCE']}";

                    $arData = [
                        'TYPE_ID' => 7,
                        'TYPE_CATEGORY_ID' => 0,
                        'AUTHOR_ID' => 1,
                        'ASSOCIATED_ENTITY_ID' => 0,
                        'ASSOCIATED_ENTITY_TYPE_ID' => 0,
                        'CREATED' => new \Bitrix\Main\Type\DateTime(date('Y-m-d') . ' 00:00:00', 'Y-m-d H:i:s'),
                        'COMMENT' => $comment,
                        'SETTINGS' => 'a:1:{s:9:"HAS_FILES";s:1:"N";}',
                    ];

                    $add = \Bitrix\Crm\Timeline\Entity\TimelineTable::add($arData);
                    $id = $add->getId();

                    if(!empty($id)){
                        $arDataBind = [
                            'OWNER_ID' => $id,
                            'ENTITY_ID' => $findContact['ID'],
                            'ENTITY_TYPE_ID' => 3,
                        ];

                        $dd = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::add($arDataBind);
                    }

                }catch (Exception $exception){}


                \Bitrix\Main\Diag\Debug::dumpToFile(array('fields' => $arUpd, 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegrationUpdate.txt");
            } else {
                \Bitrix\Main\Diag\Debug::dumpToFile(array('ERROR' => $ob->LAST_ERROR, 'fields' => $arUpd, 'date' => date('Y-m-d H:i:s')), "", "/local/php_interface/include/logs/oracleIntegrationUpdate.txt");
            }

        }

    }

}