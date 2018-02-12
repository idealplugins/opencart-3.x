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
class ModelExtensionPaymentSofort extends Model
{

    public $currencies = array('EUR');

    public $minimumAmount = 0.10;

    public $maximumAmount = 5000;

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/sofort');
        
        $checkTable = $this->db->query('show tables like "'. DB_PREFIX .'targetpay_sofort"');
        if(!$checkTable->num_rows){
            return false;
        }
        
        $query = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" .
            (int) $this->config->get('sofort_geo_zone_id') .
            "' AND country_id = '" . (int) $address['country_id'] .
            "' AND (zone_id = '" .
            (int) $address['zone_id'] . "' OR zone_id = '0')"
        );
        
        if ($this->config->get('sofort_total') > $total) {
            $status = false;
        } elseif (! $this->config->get('sofort_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }
        
        $configCurrency = strtoupper($this->config->get('config_currency'));
        
        if (! in_array($configCurrency, $this->currencies)) {
            $status = false;
        }
        
        $method_data = array();
        
        if ($status) {
            $method_data = array(
                'code' => 'sofort',
                'title' => $this->language->get('text_title'),
                'sort_order' => $this->config->get('sofort_sort_order'),
                'terms' => '<img src="' . $this->config->get('config_ssl') . 'catalog/view/theme/default/image/targetpay/DEB.png" style="height:30px; display:inline">'
            );
        }
        
        return $method_data;
    }
}
