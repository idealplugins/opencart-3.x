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

class ControllerExtensionPaymentCreditcard extends Targetpay
{
    public $paymentType = 'CC';
    public $paymentName = TargetPayCore::METHOD_CREDIT_CARD;
}
