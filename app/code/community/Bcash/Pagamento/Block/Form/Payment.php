<?php

class Bcash_Pagamento_Block_Form_Payment extends Mage_Payment_Block_Form
{

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('pagamento/form/payment.phtml');
    }

    public function getPaymentMethods()
    {
        return Mage::helper('pagamento/paymentMethod')->getPaymentMethods();
    }

    /*
    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }
    */
}
