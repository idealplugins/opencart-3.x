<?php

/**
 *
 *	iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 *	@file 		TargetPay Catalog Template
 *	@author		Yellow Melon B.V. / www.idealplugins.nl
 *
 */
?>
<?php echo $header; ?>
<style>
<!--
.tm-highlight {
  color: #c94c4c;
}
-->
</style>
<div class="container">
    <ul class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
    <?php } ?>
    </ul>

    <div class="col-xs-9">
        <div class="bankwire-info">
            <h2><?=$intro_thx;?></h2>
            <p><?=$intro_l1?></p>
            <p><?=$intro_l2?></p>
            <p><?=$intro_l3?></p>
        </div>
    </div>
</div>
<?php echo $footer; ?>