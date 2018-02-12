<?php

/**
 *
 *    iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * @file        TargetPay Admin Controller
 * @author        Yellow Melon B.V. / www.mrcashplugins.nl
 *
 */
require_once ("../system/library/targetpay.class.php");
require_once ("targetpay.php");

class ControllerExtensionPaymentMrcash extends TargetPayAdmin
{
    protected $error = array();
    protected $type = TargetPayCore::METHOD_MRCASH;
}
