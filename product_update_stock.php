<?php
/*
* 2018-Alex Ll
*
* NOTICE OF LICENSE
*
* This source file is subject to the GNU GENERAL PUBLIC LICENSE (GPL-3.0)
* It is available through the world-wide-web at this URL:
* https://opensource.org/licenses/GPL-3.0
*
*  @author Alex Ll
*  @license    https://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE (GPL-3.0)
*/
if (!defined('_PS_VERSION_'))
{
	exit;
}
class product_update_stock extends Module {
	
	public function __construct() {
		$this->name = 'product_update_stock';
		$this->tab = 'administration';
		$this->version = '1.1.0';
		$this->author = 'Alex Ll';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		parent::__construct();

		$this->secure_key = Tools::encrypt($this->name);

		$this->displayName = $this->l('Product updates on stock changes');
		$this->description = $this->l('Product active status and default product combination update when stock changes.');
		
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	public function install() {
		if (!parent::install() || !$this->registerHook('actionUpdateQuantity'))
			return false;
		return true;
	}
	
	public function uninstall() {
		if (!parent::uninstall() || !$this->unregisterHook('actionUpdateQuantity'))
			return false;
		return true;
	}
	
    public function hookactionUpdateQuantity($params) {
		$product_attribute = (int)$params['id_product_attribute'];
		
		if($product_attribute == 0){
			//If updated product stock
			
			$product = new Product((int)$params['id_product'], true);
			
			//Verify if should toggle active product if has or no stock
			if($product->id != null /*if null product was deleted*/
					&& ($product->quantity <= 0 && $product->active) || ($product->quantity > 0 && !$product->active)) {
				$this->toggleActive($product);
			}
		}else{
			//Is product combintion
			
			$id_product = (int)$params['id_product'];
			$updatedCombination = new Combination($product_attribute);
			
			if($updatedCombination->default_on == 1 && $params['quantity'] == 0){
				//If no stock search other combination with stock to make default
				
				$updatedCombination->default_on = null;
				$updatedCombination->setFieldsToUpdate(array('default_on' => true));
				$updatedCombination->update(false);
				
				$newDefault = Db::getInstance()->getRow('
					SELECT MIN(pa.id_product_attribute) as `id_attr`
					FROM `'._DB_PREFIX_.'product_attribute` pa
						'.Shop::addSqlAssociation('product_attribute', 'pa').
					' LEFT JOIN '._DB_PREFIX_.'stock_available stock
						ON stock.id_product = pa.`id_product`	
						AND pa.id_product_attribute = stock.id_product_attribute '.
						StockAvailable::addSqlShopRestriction(null, null, 'stock').'
					WHERE pa.`id_product` = '.$id_product.'
					AND pa.id_product_attribute != '.$product_attribute.'
					AND stock.`quantity` > 0'
				);
				
				if ($newDefault && $newDefault['id_attr'] != null) {
					//Update new default combination
					ObjectModel::updateMultishopTable('Combination', array('default_on' => 1), 'a.id_product_attribute = '.(int)$newDefault['id_attr']);
					Product::updateDefaultAttribute($id_product);
				}
				
			}else if($updatedCombination->default_on == null && $params['quantity'] > 0){
				//If added quantity and default doesnt have stock set as default
				
				$defaultAttribute = Product::getDefaultAttribute($id_product);
				$defaultCombination = new Combination($id_defaultAttribute);
				
				//If $id_defaultAttribute is a "real" default and has stock
				if( !$defaultCombination->default_on || StockAvailable::getQuantityAvailableByProduct($id_product, $defaultAttribute) <=0){
					//Update to remove default combination
					$oldDefaultCombination = new Combination($defaultAttribute);
					
					$oldDefaultCombination->default_on = null;
					$oldDefaultCombination->setFieldsToUpdate(array('default_on' => true));
					$oldDefaultCombination->update(false);
					
					//Update new default combination
					$updatedCombination->default_on = 1;
					$updatedCombination->setFieldsToUpdate(array('default_on' => true));
					$updatedCombination->update(false);
				}
			}
		}
    }
    
    public function toggleActive($product) {	
    	$product->setFieldsToUpdate(array('active' => true));
    	$product->active = !(int)$product->active;
    	$product->update(false);
    }
}
