<?php

/**
 *
 *    iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * @file        TargetPay Admin Controller
 * @author        Yellow Melon B.V. / www.paypalplugins.nl
 *
 */
require_once ("../system/library/targetpay.class.php");
require_once ("targetpay.php");

class ControllerExtensionPaymentPaypal extends TargetPayAdmin
{
    protected $error = array();
    protected $type = TargetPayCore::METHOD_PAYPAL;
}
