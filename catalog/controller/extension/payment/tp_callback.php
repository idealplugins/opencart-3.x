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

class ControllerExtensionPaymentTPCallback extends Controller
{

    private $message;

    private $errorMsg;

    public $method_mapping = array("IDE" => TargetPayCore::METHOD_IDEAL,"MRC" => TargetPayCore::METHOD_MRCASH,"DEB" => TargetPayCore::METHOD_SOFORT,"WAL" => TargetPayCore::METHOD_PAYSAFE,"CC" => TargetPayCore::METHOD_CREDIT_CARD,"AFP" => TargetPayCore::METHOD_AFTERPAY,"BW" => TargetPayCore::METHOD_BANKWIRE,"PYP" => TargetPayCore::METHOD_PAYPAL);

    /**
     * Handle payment result from report url
     * /index.php?route=extension/payment/tp_callback/report&payment_type=...&order_id=...
     * $_POST['trxid']
     * $_POST['payment_type']
     *
     * Handle report URL
     */
    public function report()
    {
        $payment_type = (!empty($this->request->get['payment_type'])) ? $this->request->get['payment_type'] : null;
        switch ($payment_type) {
            case 'AFP':
                $trxid = (!empty($this->request->post["invoiceID"])) ? $this->request->post["invoiceID"] : null;
                break;
            case 'PYP':
                $trxid = (!empty($this->request->post["acquirerID"])) ? $this->request->post["acquirerID"] : null;
                break;
            default:
                $trxid = (!empty($this->request->post["trxid"])) ? $this->request->post["trxid"] : null;
        }
        $order_id = (!empty($this->request->get["order_id"])) ? $this->request->get["order_id"] : null;
        
        if (empty($order_id) || empty($payment_type) || empty($trxid)) {
            $this->log->write('TargetPay tp_callback(), Invalid request');
            exit("Invalid request");
        }

        if (! $this->execReport($order_id, $trxid, $payment_type)) {
            echo $this->errorMsg;
        }
        echo $this->message;
        exit('done');
    }

    /**
     * /index.php?route=extension/payment/tp_callback/returnurl&payment_type=...&order_id=...&trxid=...
     */
    public function returnurl()
    {
        $payment_type = (!empty($this->request->get['payment_type'])) ? $this->request->get['payment_type'] : null;
        switch ($payment_type) {
            case 'AFP':
                $trxid = (!empty($this->request->get["invoiceID"])) ? $this->request->get["invoiceID"] : null;
                break;
            case 'PYP':
                $trxid = (!empty($this->request->get["paypalid"])) ? $this->request->get["paypalid"] : null;
                break;
            default:
                $trxid = (!empty($this->request->get["trxid"])) ? $this->request->get["trxid"] : null;
        }
        $order_id = (!empty($this->request->get["order_id"])) ? $this->request->get["order_id"] : null;
        
        if (empty($order_id) || empty($payment_type) || empty($trxid)) {
            $this->log->write('TargetPay tp_callback(), Invalid request');
            exit("Invalid request");
        }
        
        if ($this->execReport($order_id, $trxid, $payment_type)) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            $this->log->write($this->errorMsg);
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }
    }

    /**
     *
     */
    public function execReport($order_id, $trxid, $payment_type)
    {
	    $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        // Array mapping
        $var_type = $this->method_mapping[$payment_type]; // output: ideal
        $conf_var_type = $setting_name . $this->method_mapping[$payment_type]; // output: payment_ideal
        
        if ($this->isOcTagetPay($order_id, $trxid, $var_type) == true) {
            $this->message = 'Already paid';
            return true;
        }
        
        $this->load->model('checkout/order');

        $rtlo = ($this->config->get($conf_var_type . '_rtlo')) ? $this->config->get($conf_var_type . '_rtlo') : TargetPayCore::DEFAULT_RTLO; // Default TargetPay
        
        $targetPay = new TargetPayCore($payment_type, $rtlo, "nl", $this->config->get($conf_var_type . '_test'));
        $targetPay->checkPayment($trxid);
        
        if ($targetPay->getPaidStatus()) {
            $this->updateOcTagetPay($trxid, $var_type);

            $orderComment = '';
            $order_status_id = $this->config->get($conf_var_type . '_pending_status_id');
            if (! $order_status_id) {
                $order_status_id = 1;
            } // Default to 'pending' after payment
            if ($payment_type == 'BW' && $targetPay->getBankwireAmountPaid() < $targetPay->getBankwireAmountDue()) {
                // Partial
                $order_status_id = $this->config->get($conf_var_type . '_partial_status_id');
                if (empty($order_status_id)) {
                    $order_status_id = 1;
                }
                $orderComment = 'PARTIAL PAYMENT RECEIVED: '
                    . number_format((int)$targetPay->getBankwireAmountPaid() / 100, 2) .
                    ' of ' . number_format((int)$targetPay->getBankwireAmountDue() / 100, 2);
            }

            $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $orderComment);
            $this->message = "Paid... order_id = $order_id, trxid = $trxid, payment_type = $payment_type order_comment = $orderComment\n";
            return true;
        } else {
            $this->errorMsg = "Not paid: " . $targetPay->getErrorMessage() . "... ";
            $this->log->write($this->errorMsg);
            
            return false;
        }
    }

    /**
     * Check if transaction has paid in oc_targetpay* tables
     *
     * @param unknown $order_id            
     * @param unknown $txid            
     * @param unknown $var_type            
     * @return boolean
     */
    public function isOcTagetPay($order_id, $txid, $var_type)
    {
        $sql = "SELECT count(*) as total FROM `" . DB_PREFIX . TargetPayCore::TARGETPAY_PREFIX . $var_type . "` WHERE `order_id`='" . $this->db->escape($order_id) . "' AND `" . $var_type . "_txid`='" . $this->db->escape($txid) . "' AND `paid` is null LIMIT 1";
        $result = $this->db->query($sql);
        
        return $result->rows[0]['total'] > 0 ? false : true;
    }

    /**
     * Update paid status based on txid in database
     */
    public function updateOcTagetPay($trxid, $var_type)
    {
        $sql = "UPDATE `" . DB_PREFIX . TargetPayCore::TARGETPAY_PREFIX . $var_type . "` SET `paid`=now() WHERE `" . $var_type . "_txid`='" . $trxid . "'";
        
        $this->db->query($sql);
    }
}
