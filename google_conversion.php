<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.filesystem.file');
jimport( 'joomla.html.parameter' );


JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');

class plgPCVGoogle_conversion extends CMSPlugin
{
	function __construct(& $subject, $config) {
		parent :: __construct($subject, $config);
		$this->loadLanguage();
		$lang = Factory::getLanguage();
		//$lang->load('com_phocacart.sys');
		$lang->load('com_phocacart');
	}

	public function onPCVonInfoViewDisplayContent($context, &$infoData, &$infoAction, $eventData) {

		$p 					= [];
		$pC 				= [];
        $pCp 				= PhocacartUtils::getComponentParameters();
        $pC['store_title'] 	= $pCp->get('store_title', '');

		$p['enable_google_analytics_purchase'] = $this->params->get('enable_google_analytics_purchase', 1);

		if ($p['enable_google_analytics_purchase'] == 0) {
			return false;
		}

		$forceCurrency = 0;
		/*if ($p['currency_id'] != ''){
			$forceCurrency = (int)$p['currency_id'];
		}*/

		if (!isset($infoData['user_id'])) { $infoData['user_id'] = 0;}

		if (isset($infoData['order_id']) && (int)$infoData['order_id'] > 0 && isset($infoData['order_token']) && $infoData['order_token'] != '') {
			$order = PhocacartOrder::getOrder($infoData['order_id'], $infoData['order_token'], $infoData['user_id']);

			// $infoAction == 5 means that the order is cancelled, so no conversion
			if (isset($order['id']) && (int)$order['id'] > 0 && $infoAction != 5) {
				$orderProducts = PhocacartOrder::getOrderProducts($order['id']);
				$orderUser = PhocacartOrder::getOrderUser($order['id']);
				$orderTotal = PhocacartOrder::getOrderTotal($order['id'], ['sbrutto', 'snetto', 'pbrutto', 'pnetto', 'tax']);





				if (!empty($orderProducts)) {

					$price = new PhocacartPrice();

					$deliveryPrice = 0;
					if (isset($orderTotal['sbrutto']['amount']) && $orderTotal['sbrutto']['amount'] > 0) {
						$deliveryPrice = $price->getPriceFormatRaw($orderTotal['sbrutto']['amount'], 0, 0, $forceCurrency, 2, '.', '');
					} else if (isset($orderTotal['snetto']['amount']) && $orderTotal['snetto']['amount'] > 0) {
						$deliveryPrice = $price->getPriceFormatRaw($orderTotal['snetto']['amount'], 0, 0, $forceCurrency, 2, '.', '');
					}

					$value = $price->getPriceFormatRaw($order['total_amount'], 0, 0, 0, 2, '.', '');



					$s   = [];
					$s[] = 'gtag(\'event\', \'purchase\', {';
					$s[] = ' "transaction_id": "'.$order['order_number'].'",';
					$s[] = ' "affiliation": "'.$pC['store_title'].'",';
					$s[] = ' "value": '.$value.',';
					$s[] = ' "currency": "'.$order['currency_code'].'",';
					$s[] = ' "tax": '.$orderTotal['tax']['amount'].',';
					$s[] = ' "shipping": '.$deliveryPrice.',';
					$s[] = ' "items": [';

					$i = 0;
					foreach ($orderProducts as $k => $v) {
						$productPrice = $price->getPriceFormatRaw($v['brutto'], 0, 0, $forceCurrency, 2, '.', '');
						$brand        = PhocacartManufacturer::getManufacturers((int)$v['product_id']);

						$productBrand = '';
						if (isset($brand[0]->title)) {
							$productBrand = $brand[0]->title;
						}

						$category = PhocacartCategory::getCategoryTitleById((int)$v['product_id']);
						$productCategory = '';
						if (isset($category->title)) {
							$productCategory = $category->title;
						}

						$attributes = PhocacartOrder::getOrderAttributesByOrderedProductId((int)$v['id']);

						$productAttribute = '';
						if (!empty($attributes)) {
							$j = 0;
							foreach ($attributes as $k2 => $v2) {

								if ($j > 0) {
									$productAttribute .= ', ';
								}

								$divider = '';
								if (isset($v2['attribute_title']) && $v2['attribute_title'] != '') {

									$productAttribute .= $v2['attribute_title'];
									$divider =': ';

								}
								if (isset($v2['option_title']) && $v2['option_title'] != '') {
									$productAttribute .= $divider . $v2['option_title'];
								}
								$j++;
							}
						}

						$s[] = ' {';
						$s[] = ' "id": "' . (int)$v['product_id'] . '",';
						$s[] = ' "name": "' . addslashes($v['title']) . '",';
						$s[] = ' "list_name": "' . Text::_('PLG_PCV_GOOGLE_CONVERSION_PURCHASE') . '",';
						if ($productBrand != ''){
							$s[] = ' "brand": "'.$productBrand.'",';
						}
						if ($productCategory != ''){
							$s[] = ' "category": "'.$productCategory.'",';
						}

						if ($productAttribute != ''){
							$s[] = ' "variant": "'. addslashes($productAttribute) .'",';
						}

						$s[] = ' "list_position": '.$i.',';
						$s[] = ' "quantity": '.(int)$v['quantity'].',';
						$s[] = ' "price": \''.$productPrice.'\'';
						$s[] = ' },';
						$i++;
					}

					$s[] = ' ]';
					$s[] = '});';

					Factory::getDocument()->addScriptDeclaration(implode("\n", $s));
				}


			}
		}

		/*
		$output = array();
		$output['content'] = '';

		return $output;
		*/
	}

}
?>
