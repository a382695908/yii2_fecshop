<?php  $shippings = $parentThis['shippings'];   ?>
<div class="onestepcheckout-shipping-method">
	<p class="onestepcheckout-numbers onestepcheckout-numbers-2">Shipping method</p>
	<div class="onestepcheckout-shipping-method-block">    
		<dl class="shipment-methods">
			<?php if(!empty($shippings) &&  is_array($shippings)){ ?>
			<?php 	foreach($shippings as $shipping){ ?>
			
			<div class="shippingmethods">
				<div class="flatrate"><?= $shipping['label'] ?></div>
				<div>
					<input data-role="none" <?= $shipping['checked'] ? 'checked="checked"' : '' ?> type="radio" id="s_method_flatrate_flatrate<?= $shipping['shipping_i'] ?>" value="<?= $shipping['method'] ?>" class="validate-one-required-by-name" name="shipping_method">
					<label for="s_method_flatrate_flatrate<?= $shipping['shipping_i'] ?>"><?= $shipping['name'] ?>
						<strong>                 
							<span class="price"><?= $shipping['cost'] ?></span>
						</strong>
					</label>
				</div>
			</div>
			<?php 	} ?>
			<?php } ?>
		</dl>
	</div>
</div>