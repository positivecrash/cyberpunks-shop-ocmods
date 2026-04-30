<?php
class ControllerExtensionCyberpunksCheckoutFacade extends Controller {
	public function payment() {
		return $this->load->controller('extension/module/cyberpunks_checkout_facade/payment');
	}
}

