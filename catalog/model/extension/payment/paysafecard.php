<?php

/**
 *
 *  iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 *  @file TargetPay Catalog Model
 *  @author Yellow Melon B.V. / www.idealplugins.nl
 *
 */
require_once("system/library/targetpay.class.php");

class ModelExtensionPaymentPaysafecard extends Model
{

    public $currencies = array('EUR');

    public $minimumAmount = 0.10;

    public $maximumAmount = 150;

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/paysafecard');
        
        $checkTable = $this->db->query('show tables like "'. DB_PREFIX .'targetpay_paysafecard"');
        if(!$checkTable->num_rows){
            return false;
        }
        
        $query = $this->db->query(
            "SELECT * FROM " .
            DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" .
            (int) $this->config->get('paysafecard_geo_zone_id') .
            "' AND country_id = '" . (int) $address['country_id'] .
            "' AND (zone_id = '" . (int) $address['zone_id'] .
            "' OR zone_id = '0')"
        );
        
        if ($this->config->get('paysafecard_total') > $total) {
            $status = false;
        } elseif (! $this->config->get('paysafecard_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }
        
        if (! in_array(strtoupper($this->config->get('config_currency')), $this->currencies)) {
            $status = false;
        }
        
        $method_data = array();
        
        if ($status) {
            $method_data = array(
                'code' => 'paysafecard',
                'title' => $this->language->get('text_title'),
                'sort_order' => $this->config->get('paysafecard_sort_order'),
                'terms' => '<img src="' . $this->config->get('config_ssl') . 'catalog/view/theme/default/image/targetpay/WAL.png" style="height:30px; display:inline">'
                
            );
        }
        
        return $method_data;
    }
}
