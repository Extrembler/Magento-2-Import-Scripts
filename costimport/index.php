<?php
/**
 * @author Gaurang Padhiyar <gaurangpadhiyar1993@gmail.com>
 * @website https://coffeewithmagento.blogspot.com
 */
ini_set('memory_limit', '1G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
die("EXIT");
// Capture warning / notice as exception
set_error_handler('ctv_exceptions_error_handler');
function ctv_exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
require __DIR__ . '/../../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');
// File Name at import Folder
const FILENAME = 'Extrembler';
/**************************************************************************************************/
// UTILITY FUNCTIONS - START
/**************************************************************************************************/
function _extremblerLog($data, $includeSep = false)
{
    $fileName = getcwd() . '/log/'.FILENAME.'-m2-extrembler-import-cost.log';
    if ($includeSep) {
        $separator = str_repeat('=', 70);
        file_put_contents($fileName, $separator . '<br />' . PHP_EOL,  FILE_APPEND | LOCK_EX);
    }
    file_put_contents($fileName, $data . '<br />' .PHP_EOL,  FILE_APPEND | LOCK_EX);
}
function logAndPrint($message, $separator = false)
{
    _extremblerLog($message, $separator);
    if (is_array($message) || is_object($message)) {
        print_r($message);
    } else {
        echo $message . '<br />' . PHP_EOL;
    }
    if ($separator) {
        echo str_repeat('=', 70) . '<br />' . PHP_EOL;
    }
}
function getIndexofHeader($field)
{
    global $headers;
    $index = array_search($field, $headers);
    if ( !strlen($index)) {
        $index = -1;
    }
    return $index;
}
function readCsvRows($csvFile)
{
    $rows = [];
    $fileHandle = fopen($csvFile, 'r');
    while(($row = fgetcsv($fileHandle, 0, ',', '"', '"')) !== false) {
        $rows[] = $row;
    }
    fclose($fileHandle);
    return $rows;
}
function _getResourceConnection()
{
    global $obj;
    return $obj->get('Magento\Framework\App\ResourceConnection');
}
function _getReadConnection()
{
    return _getConnection('core_read');
}
function _getWriteConnection()
{
    return _getConnection('core_write');
}
function _getConnection($type = 'core_read')
{
    return _getResourceConnection()->getConnection($type);
}
function _getTableName($tableName)
{
    return _getResourceConnection()->getTableName($tableName);
}
function _getAttributeId($attributeCode)
{
    $connection = _getReadConnection();
    $sql = "SELECT attribute_id FROM " . _getTableName('eav_attribute') . " WHERE entity_type_id = ? AND attribute_code = ?";
    return $connection->fetchOne(
        $sql,
        [
            _getEntityTypeId('catalog_product'),
            $attributeCode
        ]
    );
}
function _getEntityTypeId($entityTypeCode)
{
    $connection = _getConnection('core_read');
    $sql        = "SELECT entity_type_id FROM " . _getTableName('eav_entity_type') . " WHERE entity_type_code = ?";
    return $connection->fetchOne(
        $sql,
        [
            $entityTypeCode
        ]
    );
}
function _getIdFromSku($sku)
{
    $connection = _getConnection('core_read');
    $sql        = "SELECT entity_id FROM " . _getTableName('catalog_product_entity') . " WHERE sku = ?";
    return $connection->fetchOne(
        $sql,
        [
            $sku
        ]
    );
}
function checkIfSkuExists($sku)
{
    $connection = _getConnection('core_read');
    $sql        = "SELECT COUNT(*) AS count_no FROM " . _getTableName('catalog_product_entity') . " WHERE sku = ?";
    return $connection->fetchOne($sql, [$sku]);
}
function updateCost($sku, $cost, $storeId = 0)
{
    $connection     = _getWriteConnection();
    $entityId       = _getIdFromSku($sku);
    $attributeId    = _getAttributeId('cost');
    $sql = "INSERT INTO " . _getTableName('catalog_product_entity_decimal') . " (attribute_id, store_id, entity_id, value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)";
    $connection->query(
        $sql,
        [
            $attributeId,
            $storeId,
            $entityId,
            $cost
        ]
    );
}
/**************************************************************************************************/
// UTILITY FUNCTIONS - END
/**************************************************************************************************/
try {
    $csvFile        = 'import/'.FILENAME.'.csv'; #EDIT - The path to import CSV file (Relative to Magento2 Root)
    $csvData        = readCsvRows(getcwd() . '/' . $csvFile);
    $headers        = array_shift($csvData);
    $count   = 0;
    foreach($csvData as $_data) {
        $count++;
        $sku   = $_data[getIndexofHeader('sku')];
        $cost = $_data[getIndexofHeader('cost')];
        if ( ! checkIfSkuExists($sku)) {
            $message =  $count .'. FAILURE:: Product with SKU (' . $sku . ') doesn\'t exist.';
            logAndPrint($message);
            continue;
        }
        try {
            updateCost($sku, $cost);
            $message = $count . '. SUCCESS:: Updated SKU (' . $sku . ') with cost (' . $cost . ')';
            logAndPrint($message);
        } catch(Exception $e) {
            $message =  $count . '. ERROR:: While updating  SKU (' . $sku . ') with cost (' . $cost . ') => ' . $e->getMessage();
            logAndPrint($message);
        }
    }
} catch (Exception $e) {
    logAndPrint(
        'EXCEPTION::' . $e->getTraceAsString()
    );
}
