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

class ControllerExtensionPaymentIdeal extends Targetpay
{

    public $paymentType = 'IDE';
    public $paymentName = TargetPayCore::METHOD_IDEAL;
    
    public function setAdditionParameter($targetPay, $order = null)
    {
        if (! empty($this->request->post['bank_id'])) {
            $targetPay->setBankId($this->request->post['bank_id']);
        }
        return true;
    }
    
    public function setListConfirm($data)
    {
        $targetCore = new TargetPayCore($this->paymentType);
        $data['custom'] = $this->session->data['order_id'];
        $data['banks'] = $targetCore->getBankList();
        return $data;
    }
}
