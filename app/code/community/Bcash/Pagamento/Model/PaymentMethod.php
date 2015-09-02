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

    protected $transaction;

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
            Mage::log(print_r($result, true));
            $response = $result['response'];
            $payment_method = $result['payment_method'];
            $installments = $result['installments'];

            /*
            1 – Em andamento
            3 – Aprovada
            4 – Concluída
            5 – Disputa
            6 – Devolvida
            7 – Cancelada
            8 – Chargeback
            */

            //TODO: Salvar o PEDIDO em caso de SUCESSO e adicionar os dados da Transação
            if ($response->status != 1) {

                $setIsNotified = false;
                switch($response->status)
                {
                    case 3: //3 – Aprovada
                    case 4: //4 – Concluída
                        $state = Mage_Sales_Model_Order::STATE_PROCESSING;
                        $setIsNotified = true;
                        break;
                    case 6://6 – Devolvida
                        $state = Mage_Sales_Model_Order::STATE_HOLDED;
                        break;
                    case 7://7 – Cancelada
                    case 8://8 – Chargeback
                        $state = Mage_Sales_Model_Order::STATE_CANCELED;
                        break;
                    default:
                        $state = null;
                        break;
                }

                if(!is_null($state)){
                    $stateObject->setState($state);
                    $stateObject->setStatus($state);
                    $stateObject->setIsNotified($setIsNotified);
                }
            }

            $this->transaction->quoteBcash
                ->setTransactionIdBcash($response->transactionId)
                ->setStatusBcash($response->status)
                ->setDescriptionStatusBcash($response->descriptionStatus)
                ->setPaymentLinkBcash($response->paymentLink)
                ->save();

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
        $this->transaction = new Bcash_Pagamento_Helper_Transaction();
        return $this->transaction->startTransaction();
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
        $params['installments_bcash'] = isset($params['installments_bcash']) ?$params['installments_bcash']:1;
        //TODO: Adicionar Desconto ao Pedido caso 1x Credito, Boleto ou TEF (configurados no Backend)
        $discount = 0;
        if ($params['installments_bcash'] == 1) {
            $discount = $this->calculateDiscount($params['payment-method']);
        }
        $this->addDiscountToQuote($discount);
        return $result;
    }

    public function calculateDiscount($payment_method)
    {
        $transaction = new Bcash_Pagamento_Helper_Transaction();
        return $transaction->calculateDiscount($payment_method);
    }

    public function addDiscountToQuote($discountAmount = 0)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $objShippingAddress = $cart->getQuote()->getShippingAddress();
        $objShippingAddress->setDiscountDescription('Meio de pagamento selecionado');
        $objShippingAddress->addTotal(array(
                'code' => 'discount',
                'title' => "Desconto",
                'value' => -$discountAmount,
            ));
        $totalDiscountAmount = $discountAmount;
        $subtotalWithDiscount = $discountAmount;
        $baseTotalDiscountAmount = $discountAmount;
        $baseSubtotalWithDiscount = $discountAmount;
        $objShippingAddress->setDiscountAmount($totalDiscountAmount);
        $objShippingAddress->setSubtotalWithDiscount($subtotalWithDiscount);
        $objShippingAddress->setBaseDiscountAmount($baseTotalDiscountAmount);
        $objShippingAddress->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
        $objShippingAddress->setGrandTotal($objShippingAddress->getGrandTotal() - $objShippingAddress->getDiscountAmount());
        $objShippingAddress->setBaseGrandTotal($objShippingAddress->getBaseGrandTotal() - $objShippingAddress->getBaseDiscountAmount());
    }
}
