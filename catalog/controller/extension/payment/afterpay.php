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

class ControllerExtensionPaymentAfterpay extends Targetpay
{
    public $paymentType = 'AFP';
    public $paymentName = TargetPayCore::METHOD_AFTERPAY;
}
