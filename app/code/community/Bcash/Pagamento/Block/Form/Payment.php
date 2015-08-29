<?php

/**
 * Class Bcash_Pagamento_Block_Form_Payment
 */
class Bcash_Pagamento_Block_Form_Payment extends Mage_Payment_Block_Form
{


    /**
     * Instancia o template referente ao método de pagamento
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('pagamento/form/payment.phtml');
    }

    /**
     * @return mixed
     */
    public function getPaymentMethods()
    {
        //return Mage::helper('pagamento/paymentMethod')->getPaymentMethods();
    }

}
