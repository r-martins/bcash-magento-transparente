<?php


/**
 * Class Bcash_Pagamento_Model_PaymentMethod
 */
class Bcash_Pagamento_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @var string
     */
    protected $_code = 'pagamento';
    /**
     * @var string
     */
    //Disable multi-shipping for this payment module.
    protected $_canUseForMultishipping  = false;
    /**
     * @var string
     */
    protected $_formBlockType = 'pagamento/form_payment';

    //Flag executa o método initalize() com o checkout completo.
    /**
     * @var bool
     */
     protected $_isInitializeNeeded = true;

    /**
     * Retornar URL para redirecionar o cliente.
     * Chamado depois que o botão é clicado.
     * Chamado após a criação e registro do pedido "Order".
     * @return string
     */
    /*
    public function getOrderPlaceRedirectUrl()
    {
        /* Mage log is your friend.
         * While it shouldn't be on in production,
         * it makes debugging problems with your api much easier.
         * The file is in magento-root/var/log/system.log
         */
        /*
        Mage::log('Called custom ' . __METHOD__);
        $url = $this->getConfigData('redirecturl');
        return $url;
    }*/

    /**
     *
     * <payment_action>sale</payment_action>
     * Inicializa o método de pagamento. Chamado quando a compra é completa.
     * Objeto "Order" será criado após a chamada deste método.
     *
     * @param string $paymentAction
     * @param Varien_Object $stateObject
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        Mage::log('Called ' . __METHOD__ . ' with payment ' . $paymentAction);
        Mage::log('Payment visitor: ' . Mage::helper('core/http')->getRemoteAddr());
        parent::initialize($paymentAction, $stateObject);

        if ($paymentAction != 'sale') {
            return $this;
        }

        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        try {
            $this->_customBeginPayment();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Inicializa a transação atual via SDK Api Bcash.
     * Respond with token
     * @throws SoapFault
     * @throws Mage_Exception
     * @throws Exception
     */
    protected function _customBeginPayment()
    {
        $transaction = new Bcash_Pagamento_Helper_Transaction();
        $transaction->startTransaction();
    }
}
