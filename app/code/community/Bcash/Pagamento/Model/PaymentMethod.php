<?php
require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php");

use Bcash\Domain\PaymentMethodEnum;
use Bcash\Domain\CurrencyEnum;
use Bcash\Domain\StateEnum;
use Bcash\Domain\ShippingTypeEnum;
use Bcash\Domain\Model;
use Bcash\Domain\CreditCard;
use Bcash\Domain\Address;
use Bcash\Domain\Customer;
use Bcash\Domain\Product;
use Bcash\Domain\TransactionRequest;
use Bcash\Domain\PaymentMethod;
use Bcash\Domain\DependentTransaction;
use Bcash\Service\Payment;
use Bcash\Exception\ConnectionException;
use Bcash\Exception\ValidationException;

class Bcash_Pagamento_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'pagamento';
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canAuthorize = true;
    protected $_canOrder = true;
    protected $_formBlockType = 'pagamento/form_payment';

    //Flag executa o método initalize() com o checkout completo.
    protected $_isInitializeNeeded = true;

    //Variaveis de Transação
    private $email;
    private $consumer_key;
    private $sandbox;
    private $items;
    private $billingData;
    private $grandTotal;
    private $transactionRequest;
    private $quoteIdTransaction;
    private $dependents;
    private $quote;

    /**
     * Return URL to redirect the customer to.
     * Called after 'place order' button is clicked.
     * Called after order is created and saved.
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        /* Mage log is your friend.
         * While it shouldn't be on in production,
         * it makes debugging problems with your api much easier.
         * The file is in magento-root/var/log/system.log
         */
        mage::log('Called custom ' . __METHOD__);
        $url = $this->getConfigData('redirecturl');
        return $url;
    }

    /**
     *
     * <payment_action>Sale</payment_action>
     * Initialize payment method. Called when purchase is complete.
     * Order is created after this method is called.
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
        $this->email = $this->getConfigData('email');
        $this->consumer_key = $this->getConfigData('consumer_key');
        $this->sandbox = $this->getConfigData('sandbox');
        parent::initialize($paymentAction, $stateObject);
        //Payment is also used for refund and other backend functions.
        //Verify this is a sale before continuing.
        if ($paymentAction != 'sale') {
            return $this;
        }
        //Set the default state of the new order.
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; // state now = 'pending_payment'
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
        //Extract order details and send to mockpay api. Get api token and save it to checkout/session.
        try {
            $this->_customBeginPayment();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            Mage::throwException($e->getMessage());
        }
        return $this;
    }

    /**
     *
     * Extract cart/quote details and send to api.
     * Respond with token
     * @throws SoapFault
     * @throws Mage_Exception
     * @throws Exception
     */
    protected function _customBeginPayment()
    {
        //Retrieve cart/quote information.
        $sessionCheckout = Mage::getSingleton('checkout/session');
        $quoteId = $sessionCheckout->getQuoteId();
        //The quoteId will be removed from the session once the order is placed.
        //If you need it, save it to the session yourself.
        $sessionCheckout->setData('QuoteId', $quoteId);
        $this->quote = Mage::getModel("sales/quote")->load($quoteId);
        $grandTotal = floatval($this->quote->getData('grand_total'));
        $subTotal = floatval($this->quote->getSubtotal());
        $shippingHandling = floatval($grandTotal - $subTotal);
        //Set required items.
        $this->billingData = $this->quote->getBillingAddress()->getData();
        $this->grandTotal = $grandTotal;
        $this->quoteIdTransaction = (str_pad($quoteId, 9, 0, STR_PAD_LEFT));
        //Retrieve items from the quote.
        $this->items = $this->quote->getItemsCollection()->getItems();
        $this->transactionRequest = $this->createTransactionRequest();
        $this->setShipping();
        $this->setPaymentMethod();
        $payment = new Payment($this->consumer_key);
        if ($this->sandbox) {
            $payment->enableSandBox(true);
        }

        try {
            $response = $payment->create($this->transactionRequest);
            //Tratar retorno
            $response['transactionId'];//224
            $response['orderId'];//000000700
            $response['status'];//1
            $response['descriptionStatus'];//Em+andamento
            $response['paymentLink'];//https%3A%2F%2Fsandbox.bcash.com.br%2Fcheckout%2FBoleto%2FImprime%2F224%2F0z0ajEHp0RqdnYydaRlPFkCME2cuwt
        } catch (ValidationException $e) {
            Mage::throwException($e->getErrors());
        } catch (ConnectionException $e) {
            Mage::throwException($e->getErrors());
        }

        return $this;
    }

    public function setPaymentMethod()
    {
        $cards  = array(PaymentMethodEnum::VISA, PaymentMethodEnum::MASTERCARD, PaymentMethodEnum::AMERICAN_EXPRESS, PaymentMethodEnum::AURA, PaymentMethodEnum::DINERS, PaymentMethodEnum::HIPERCARD, PaymentMethodEnum::ELO);
        $boleto = PaymentMethodEnum::BANK_SLIP;
        $tefs   = array(PaymentMethodEnum::BB_ONLINE_TRANSFER, PaymentMethodEnum::BRADESCO_ONLINE_TRANSFER, PaymentMethodEnum::ITAU_ONLINE_TRANSFER, PaymentMethodEnum::BANRISUL_ONLINE_TRANSFER, PaymentMethodEnum::HSBC_ONLINE_TRANSFER);
        $payment_method = Mage::app()->getRequest()->getPost('payment-method');
        $this->transactionRequest->setPaymentMethod($payment_method);
        if (in_array($payment_method, $cards)) {
            $this->transactionRequest->setCreditCard($this->createCreditCard());
        }
    }


    public function createAddress()
    {
        $address = $this->quote->getShippingAddress()->getData();
        $addressObj = new Address();
        $addressObj->setAddress($address['street']);
        $addressObj->setNumber('SN');
        $addressObj->setComplement('');
        $addressObj->setNeighborhood('');
        $addressObj->setCity($address['city']);
        $addressObj->setState($address['region']);
        $addressObj->setZipCode($address['postcode']);
        return $addressObj;
    }

    public function createBuyer()
    {
        $customer_id = $this->quote->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($customer_id);
        $customerData = $customer->getData();
        $cpf_cnpj_bcash = Mage::app()->getRequest()->getPost('cpf_cnpj_bcash');
        $buyer = new Customer();
        $buyer->setMail($customerData['email']);
        $name = $customerData['firstname'];
        $name .= $customerData['middlename'] ? ' ' . $customerData['middlename'] : '';
        $name .= $customerData['lastname'] ? ' ' . $customerData['lastname'] : '';
        $buyer->setName($name);
        $buyer->setCpf($cpf_cnpj_bcash);
        $buyer->setPhone($this->completePhone());
        $buyer->setAddress($this->createAddress());
        return $buyer;
    }

    public function completePhone()
    {
        $address  = $this->quote->getBillingAddress()->getData();
        $ddd_bcash = Mage::app()->getRequest()->getPost('ddd_bcash');
        $phone_bcash = Mage::app()->getRequest()->getPost('phone_bcash');
        $full_phone = $ddd_bcash . $phone_bcash;
        if (!$ddd_bcash) {
            $length = strlen($address['telephone']);
            if ($length > 11) {
                $address['telephone'] = substr($address['telephone'], -11);
            }
            $full_phone = $address['telephone'];
        }
        return $full_phone;
    }

    public function createProduct()
    {
        $products = array();
        foreach ($this->items as $item) {
            $product = new Product();
            $cod = $item->getSku() ? $item->getSku() : $item->getId();
            $product->setCode($cod);
            $name = $item->getName();
            $product->setDescription($name);
            $qty = $item->getQty();
            $product->setAmount(intval($qty));
            $price = $item->getPrice();
            $product->setValue(floatval($price));
            array_push($products, $product);
        }
        return $products;
    }

    public function createTransactionRequest()
    {
        $url = Mage::getUrl('pagamento/notification/request');
        $transactionRequest = new Bcash\Domain\TransactionRequest();
        $transactionRequest->setSellerMail($this->email);
        $transactionRequest->setOrderId($this->quoteIdTransaction);
        $transactionRequest->setBuyer($this->createBuyer());
        $transactionRequest->setUrlNotification($url);
        $transactionRequest->setProducts($this->createProduct());
        $transactionRequest->setAcceptedContract("S");
        $transactionRequest->setViewedContract("S");
        $transactionRequest->setDependentTransactions($this->createDependentTransactions());
        return $transactionRequest;
    }

    public function setAdditionAndDiscont($addition = null, $discount = null)
    {
        if ($addition) {
            $this->transactionRequest->setAddition($addition);
        }
        if ($discount) {
            $this->transactionRequest->setDiscount($discount);
        }
    }

    public function setShipping()
    {
        $shipping = $this->quote->getShippingAddress()->getData();
        $this->transactionRequest->setShipping(floatval($shipping['shipping_amount']));
        $this->transactionRequest->setShippingType($shipping['shipping_description']);
    }

    public function createCreditCard()
    {
        $card_number_bcash = Mage::app()->getRequest()->getPost('card_number_bcash');
        $month_bcash = Mage::app()->getRequest()->getPost('month_bcash');
        $year_bcash = Mage::app()->getRequest()->getPost('year_bcash');
        $name_card_bcash = Mage::app()->getRequest()->getPost('name_card_bcash');
        $cvv_bcash = Mage::app()->getRequest()->getPost('cvv_bcash');
        $creditCard = new CreditCard();
        $creditCard->setHolder($name_card_bcash);
        $creditCard->setNumber($card_number_bcash);
        $creditCard->setSecurityCode($cvv_bcash);
        $creditCard->setMaturityMonth($month_bcash);
        $creditCard->setMaturityYear($year_bcash);
        return $creditCard;
    }

    public function createDependentTransactions()
    {
        $deps = array();
        foreach ($this->dependents as $dep) {
            $dependent = new DependentTransaction();
            $dependent->setEmail($dep['email']);
            $dependent->setValue(floatval($dep['percentual']));
            array_push($deps, $dependent);
        }
        return $deps;
    }
}
