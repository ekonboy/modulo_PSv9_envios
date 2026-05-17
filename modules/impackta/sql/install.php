<?php
/**
* 2026 gabriel rese
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    gabriel rese
*  @copyright 2026 gabriel rese
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of gabriel rese
*/
$sql = array();

// Tabla propia minima: guarda la configuracion Impackta por tienda para no mezclar
// credenciales en instalaciones multitienda.
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'impackta` (
    `id_shop` int(10) unsigned NOT NULL DEFAULT 1,
	`guid` varchar(100) NOT NULL,
    `idCliente` varchar(100) NOT NULL,
    UNIQUE KEY `impackta_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';




foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

// Si el modulo ya existia antes de soportar multitienda, se migra la tabla sin romper reinstalaciones.
$column = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'impackta` LIKE "id_shop"');
if (empty($column)) {
    if (Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'impackta` ADD `id_shop` int(10) unsigned NOT NULL DEFAULT 1 FIRST') == false) {
        return false;
    }
}

$indexes = Db::getInstance()->executeS('SHOW INDEX FROM `' . _DB_PREFIX_ . 'impackta` WHERE Key_name = "impackta_shop"');
if (empty($indexes)) {
    if (Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'impackta` ADD UNIQUE KEY `impackta_shop` (`id_shop`)') == false) {
        return false;
    }
}

return true;
