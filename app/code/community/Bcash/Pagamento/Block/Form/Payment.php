<?php

class Bcash_Pagamento_Block_Form_Payment extends Mage_Payment_Block_Form
{
	
	public function __construct()
	{
		parent::__construct();
		$this->setTemplate('pagamento/form/payment.phtml');
		//$this->addCss('css/pagamento/application.css');
		
		//Mage::getSingleton('core/session', array('name' => 'frontend'));
    	//$Block = Mage::getSingleton('core/layout');
		//$head = $Block->createBlock('Page/Html_Head');
		//$head->addCss('css/pagamento/application.css');
	}
	
	public function getPaymentMethods() {
		return Mage::helper('pagamento/paymentMethod')->getPaymentMethods();
	}
	
	/*
	protected function _prepareLayout()
   	{
		
    	//$this->getLayout()->getBlock('head')->addCss('css/pagamento/application.css');
    	return parent::_prepareLayout();
   	} */

}
