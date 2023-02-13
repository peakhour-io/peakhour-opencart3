<?php
/*
 *  @since      1.0.0
 *  @author     Peakhour.io Pty Ltd <support@peakhour.io>
 *  @copyright  Copyright (c) 2021 Peakhour Technologies, (https://www.peakhour.io)
 *  @license    https://opensource.org/licenses/GPL-3.0
 *
 * The following code is a derivative work of the code from the LiteSpeed LSCache project,
 * which is licensed GPLv3. This code therefore is also licensed under the terms
 * of the GNU Public License, verison 3.
 */

class ModelExtensionModulePeakhour extends Model {
	public function getSetting($code, $store_id = 0) {
		$setting_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$setting_data[$result['key']] = $result['value'];
			} else {
				$setting_data[$result['key']] = json_decode($result['value'], true);
			}
		}

		return $setting_data;
	}

	
	public function getSettingValue($code, $key, $store_id = 0) {

        $query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id ."' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");

		if ($query->num_rows) {
			return $query->row['value'];
		} else {
			return null;
		}
        
	}

    
    public function getESIModules($store_id = 0){

        $setting_data = $this->cache->get('peakhour_esi_modules');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_peakhour' AND `key` like 'esi%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            if($value['esi_type']>0){
                $setting_data[$result['key']] = $value;
            }
		}
		ksort($setting_data);
        return $setting_data;
    }


    public function getPages($store_id = 0){
        $setting_data = $this->cache->get('peakhour_pages');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_peakhour' AND `key` like 'page%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            $value['key'] = $result['key'];
			$setting_data[$result['key']] = $value; 
		}

        $this->cache->set('peakhour_pages', $setting_data);

        return $setting_data;
    }

    
    public function getModules($store_id = 0){
        $setting_data = $this->cache->get('peakhour_modules');
        if($setting_data){
            return $setting_data;
        }
        
		$setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_peakhour' AND `key` like 'esi%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            $value['key'] = $result['key'];
			$setting_data[$result['key']] = $value;
		}
        
        $this->cache->set('peakhour_modules', $setting_data);

		return $setting_data;
    }

    
    public function getItems($store_id = 0){
        
        $setting_data = $this->cache->get('peakhour_itemes');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value`, `serialized` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_peakhour' AND `key` not like 'esi%' AND `key` not like 'page%'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$setting_data[$result['key']] = $result['value'];
			} else {
				$setting_data[$result['key']] = json_decode($result['value'], true);
			}
		}

		return $setting_data;
    }
    
    public function isRouterExclude($router){
        
        $excludeRoutes = array(
		'checkout/cart', 
		'checkout/checkout',
		'checkout/success',
		'account/register',
		'account/login',
		'account/edit',
		'account/account',
		'account/password',
		'account/address',
		'account/address/update',
		'account/address/delete',
		'account/wishlist',
		'account/order',
		'account/download',
		'account/return',
		'account/return/insert',
		'account/reward',
		'account/voucher',
		'account/transaction',
		'account/newsletter',
		'account/logout',
		'affiliate/login',
		'affiliate/register',
		'affiliate/account',
		'affiliate/edit',
		'affiliate/password',
		'affiliate/payment',
		'affiliate/tracking',
		'affiliate/transaction',
		'affiliate/logout',
		'product/compare',
		'error/not_found');
        
        return in_array($router, $excludeRoutes);
        
    }
    
}
