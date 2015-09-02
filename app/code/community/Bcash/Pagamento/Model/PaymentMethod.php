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
    protected $_formBlockType = 'pagamento/form_payment';

    /** Flag executa o método initalize() com o checkout completo.
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canUseCheckout = true;
    //Disable multi-shipping for this payment module.
    protected $_canUseForMultishipping  = false;

    /*
    protected $_isGateway                   = false;
    protected $_canOrder                    = false;
    protected $_canAuthorize                = false;
    protected $_canCapture                  = false;
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = true;
    */

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
        $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);

        try {
            $result = $this->_customBeginPayment();
            Mage::log(print_r($result,true));
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

    /**
     * Assign data to info model instance
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        Mage::log('Assign Data with Bcash');
        $result = parent::assignData($data);

        $params = Mage::app()->getFrontController()->getRequest()->getParams();

        /**
        Array(
               [payment] => Array([method] => pagamento)
               [payment-method] => 2
               [card_number_bcash] => 5555666677778884
               [month_bcash] => 05
               [year_bcash] => 2018
               [name_card_bcash] => Flavio H Ferreira
               [cvv_bcash] => 123
               [installments_bcash] => 1
            )
         */
        //TODO: Adicionar Desconto ao Pedido caso 1x Credito, Boleto ou TEF (configurados no Backend)

        $this->addDiscountToQuote();

        return $result;
    }

    public function calculateDiscount()
    {

    }

    public function addDiscountToQuote()
    {
      $sessionCheckout = Mage::getSingleton('checkout/session');
      $quoteId = $sessionCheckout->getQuoteId();
      $quote = Mage::getModel("sales/quote")->load($quoteId);
      $quote->setDiscountAmount('2');
      $quote->collectTotals()->save();
    }
}
