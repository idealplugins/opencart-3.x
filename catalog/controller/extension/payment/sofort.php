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

require_once ("targetpay.php");


class ControllerExtensionPaymentSofort extends Targetpay
{
    public $paymentType = 'DEB';
    public $paymentName = TargetPayCore::METHOD_SOFORT;
    
    public function setAdditionParameter($targetPay, $order = null)
    {
        if (!empty($this->request->post['country_id'])) {
            $targetPay->setCountryId($this->request->post['country_id']);
        }
        return true;
    }
    
    public function setListConfirm($data)
    {
        $targetCore = new TargetPayCore($this->paymentType);
        $data['custom'] = $this->session->data['order_id'];
        $data['banks'] = $targetCore->getCountryList();
        return $data;
    }
}
