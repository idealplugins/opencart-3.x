<?php

/**
 *  iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * @file       TargetPay Catalog Controller
 * @author     Yellow Melon B.V. / www.sofortplugins.nl
 * @release    5 nov 2014
 */
require_once ("system/library/targetpay.class.php");
define('OC_VERSION', substr(VERSION, 0, 1));

class Targetpay extends Controller
{

    public $paymentType;

    public $paymentName;

    /**
     * Select bank
     */
    public function index()
    {
        $this->language->load('extension/payment/' . $this->paymentName);
        $data = [];
        
        $data['text_title'] = $this->language->get('text_title');
        $data['text_wait'] = $this->language->get('text_wait');
        
        $data['entry_bank_id'] = $this->language->get('entry_bank_id');
        $data['button_confirm'] = $this->language->get('button_confirm');
        
        $data = $this->setListConfirm($data);
        
        return $this->load->view($this->config->get('config_template') . 'extension/payment/' . $this->paymentName, $data);
    }

    /**
     * Start payment
     */
    public function send()
    {
        $paymentType = $this->paymentType;
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';

        $json = [];
        $this->load->model('checkout/order');
        
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        $rtlo = ($this->config->get($setting_name . $this->paymentName . '_rtlo')) ? $this->config->get($setting_name . $this->paymentName . '_rtlo') : TargetPayCore::DEFAULT_RTLO; // Default TargetPay

        if(!in_array(strtolower($order_info['payment_iso_code_2']), ['nl', 'be']) || !in_array(strtolower($order_info['shipping_iso_code_2']), ['nl', 'be'])) {
            $this->log->write("Invalid shipping/payment country");
            $json['error'] = "Invalid shipping/payment country";
        }
        else if ($order_info['currency_code'] != "EUR") {
            $this->log->write("Invalid currency code " . $order_info['currency_code']);
            $json['error'] = "Invalid currency code " . $order_info['currency_code'];
        } else {
            $targetPay = new TargetPayCore($paymentType, $rtlo, "nl", $this->config->get($this->paymentName . '_test'));
            $targetPay->setAmount(round($order_info['total'] * 100));
            $targetPay->setDescription("Order id: " . $this->session->data['order_id']);
            
            $this->setAdditionParameter($targetPay, $order_info);
            
            $params = array('order_id' => $this->session->data['order_id'], 'payment_type' => $paymentType);
            $targetPay->setReturnUrl(html_entity_decode($this->url->link('extension/payment/tp_callback/returnurl', $params, true)));
            $targetPay->setReportUrl(html_entity_decode($this->url->link('extension/payment/tp_callback/report', $params, true)));
            $bankUrl = $targetPay->startPayment();
            
            if (! $bankUrl) {
                $this->log->write('TargetPay start payment failed: ' . $targetPay->getErrorMessage());
                $err = ($targetPay->getErrorMessage());

                $json['error'] = 'TargetPay start payment failed: ' . $targetPay->getErrorMessage();
            } else {
                // For bankwire, after starting API, open the instruction page
                if ($paymentType == 'BW') {
                    // store order_id and moreInformation into session for instruction page
                    $this->session->data['bw_info'] = ['bw_data' => $targetPay->moreInformation,'order_total' => $order_info['total'], 'customer_email' => $order_info['email']];
                    $bankUrl = $this->url->link('extension/payment/bankwire/bwintro');
                }
                
                $this->storeTxid($targetPay->getPayMethod(), $targetPay->getTransactionId(), $this->session->data['order_id']);
                $json['success'] = $bankUrl;
            }
        }
        
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Save txid/order_id pair in database
     */
    public function storeTxid($method, $txid, $order_id)
    {
        $sql = "INSERT INTO `" . DB_PREFIX . TargetPayCore::TARGETPAY_PREFIX . $this->paymentName . "` SET " . "`order_id`='" . $this->db->escape($order_id) . "', " . "`method`='" . $this->db->escape($method) . "', `" . $this->paymentName . "_txid`='" . $this->db->escape($txid) . "'";
        
        $this->db->query($sql);
    }

    /**
     * 
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     */
    private static function format_phone($country, $phone) {
        if(empty($country)) return $phone;
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('Targetpay', $function)) {
            return self::$function($phone);
        }
        else {
            echo "unknown phone formatter for country: ". $function;
            exit;
        }
        return $phone;
    }
    
    /**
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    /**
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_bel($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    /**
     * 
     * @param unknown $street
     * @return NULL[]|string[]|unknown[]
     */
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if(!$addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
    }
    
    /**
     * 
     * @param unknown $payMethod
     * @param unknown $order
     * @param unknown $targetPay
     */
    public function setAdditionParameter($targetPay, $order = null)
    {
        if ($targetPay->getPayMethod() == 'AFP') {
            $this->additionalParametersAFP($targetPay, $order); // add addtitional params for afterpay and bankwire
        }
        if ($targetPay->getPayMethod() == 'BW') {
            $this->additionalParametersBW($targetPay, $order); // add addtitional params for afterpay and bankwire
        }
        return true;
    }

    /**
     * 
     * @param unknown $data
     * @return unknown
     */
    public function setListConfirm($data)
    {
        return $data;
    }

    /**
     * 
     * @param unknown $order
     * @param TargetPayCore $targetPay
     */
    function additionalParametersAFP(TargetPayCore $targetPay, $order)
    {
        // Supported countries are: Netherlands (NLD) and in Belgium (BEL)
        $streetParts = self::breakDownStreet($order['payment_address_1']);
        
        $targetPay->bindParam('billingstreet', $streetParts['street']);
        $targetPay->bindParam('billinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $targetPay->bindParam('billingpostalcode', $order['payment_postcode']);
        $targetPay->bindParam('billingcity', $order['payment_city']);
        $targetPay->bindParam('billingpersonemail', $order['email']);
        $targetPay->bindParam('billingpersoninitials', "");
        $targetPay->bindParam('billingpersongender', "");
        $targetPay->bindParam('billingpersonsurname', $order['payment_lastname']);
        // var_dump($order);die;
        $targetPay->bindParam('billingcountrycode', $order['payment_iso_code_3']);
        $targetPay->bindParam('billingpersonlanguagecode', $order['payment_iso_code_3']);
        $targetPay->bindParam('billingpersonbirthdate', "");
        $targetPay->bindParam('billingpersonphonenumber', self::format_phone($order['payment_iso_code_3'], $order['telephone']));
        
        $streetParts = self::breakDownStreet($order['shipping_address_1']);
        
        $targetPay->bindParam('shippingstreet', $streetParts['street']);
        $targetPay->bindParam('shippinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $targetPay->bindParam('shippingpostalcode', $order['shipping_postcode']);
        $targetPay->bindParam('shippingcity', $order['shipping_city']);
        $targetPay->bindParam('shippingpersonemail', $order['email']);
        $targetPay->bindParam('shippingpersoninitials', "");
        $targetPay->bindParam('shippingpersongender', "");
        $targetPay->bindParam('shippingpersonsurname', $order['shipping_lastname']);
        $targetPay->bindParam('shippingcountrycode', $order['shipping_iso_code_3']);
        $targetPay->bindParam('shippingpersonlanguagecode', $order['shipping_iso_code_3']);
        $targetPay->bindParam('shippingpersonbirthdate', "");
        $targetPay->bindParam('shippingpersonphonenumber', self::format_phone($order['shipping_iso_code_3'], $order['telephone']));

        // Getting the items in the order
        $invoicelines = [];
        $total_amount_by_products = 0;
        
        // Iterating through each item in the order
        foreach ($this->cart->getProducts() as $product) {
            $total_amount_by_products += $product['total'];
            $priceAfterTax = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
            $taxPercent = ($priceAfterTax / $product['price'] * 100) - 100;
            
            $invoicelines[] = [
                'productCode' => $product['product_id'],
                'productDescription' => $product['name'],
                'quantity' => $product['quantity'],
                'price' => (float)$product['total'],   //(Total, after quantity) price of this product in the order, excluding VAT (see below), decimal
                'taxCategory' => $targetPay->getTax($taxPercent)
                
            ];
        }
        if ($order['total'] - $total_amount_by_products > 0) {
            $invoicelines[] = [
                'productCode' => '000000',
                'productDescription' => "Other fees (shipping, additional fees)",
                'quantity' => 1,
                'price' => $order['total'] - $total_amount_by_products,
                'taxCategory' => 4
                
            ];
        }

        $targetPay->bindParam('invoicelines', json_encode($invoicelines));
        $targetPay->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }

    /**
     *
     * @param unknown $order            
     * @param TargetPayCore $targetPay            
     */
    function additionalParametersBW(TargetPayCore $targetPay, $order)
    {
        $targetPay->bindParam('salt', $targetPay->bwSalt);
        $targetPay->bindParam('email', $order['email']);
        $targetPay->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
}
