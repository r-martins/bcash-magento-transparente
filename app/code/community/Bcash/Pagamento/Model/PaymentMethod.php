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

    //flag which causes initalize() to run when checkout is completed.
    protected $_isInitializeNeeded = true;

    private $email;
    private $consumer_key;
    private $sandbox;

    private $items;
    private $consumer;
    private $billingData;
    private $subTotal;
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
        // access log (debug)
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
        $grandTotal = $this->quote->getData('grand_total');
        $subTotal = $this->quote->getSubtotal();
        $shippingHandling = ($grandTotal - $subTotal);
        Mage::Log("Sub Total: $subTotal | Shipping & Handling: $shippingHandling | Grand Total $grandTotal");

        //Set required items.
        $this->billingData = $this->quote->getBillingAddress()->getData();
        $this->grandTotal = $grandTotal;
        $this->quoteIdTransaction = (str_pad($quoteId, 9, 0, STR_PAD_LEFT));
        //Retrieve items from the quote.
        $this->items = $this->quote->getItemsCollection()->getItems();

        //Build urls back to our modules controller actions as required by the api.
        //$oUrl = Mage::getModel('core/url');
        //$apiHrefSuccess = $oUrl->getUrl("pagamento/payment/success");
        //$apiHrefFailure = $oUrl->getUrl("pagamento/payment/failure");
        //$apiHrefCancel  = $oUrl->getUrl("pagamento/payment/cancel");

        $this->transactionRequest = $this->createTransactionRequest();

        $this->setShipping();
        $this->setPaymentMethod();

        $payment = new Payment($this->consumer_key);

        if ($this->sandbox)
            $payment->enableSandBox(true);

        try {
            $response = $payment->create($this->transactionRequest);
            //Mage::throwException($response);
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
        if (in_array($payment_method, $cards))
        {
            $this->transactionRequest->setCreditCard($this->createCreditCard());
        }
    }

    public function order(Varien_Object $payment, $amount)
    {

        /*
        payment[method]:pagamento
        payment-method:2
        card_number_bcash:5453010000066167
        month_bcash:05
        year_bcash:2018
        name_card_bcash:Flavio H Ferreira
        cvv_bcash:123
        installments_bcash:1
        cpf_cnpj_bcash:359.298.818-20
        ddd_bcash:16
        phone_bcash:992084635
        */
        //$pay = print_r($payment, true);
        //$am = print_r($amount, true);
        //
        //$myFile = "testFile.txt";
        //$fh = fopen($myFile, 'w') or die("can't open file");
        //fwrite($fh, $pay);
        //fwrite($fh, $am);
        //fclose($fh);

        /*
        // initialize your gateway transaction as needed
        // $gateway is just an imaginary example of course
        $gateway->init( $amount,
            $payment->getOrder()->getId(),
            $returnUrl,
            ...);
        if($gateway->isSuccess()) {
            // save transaction id
            $payment->setTransactionId($gateway->getTransactionId());
        } else {
            // this message will be shown to the customer
            Mage::throwException($gateway->getErrorMessage());
        }
        */

        $payment_method = Mage::app()->getRequest()->getPost('payment-method');
        $card_number_bcash = Mage::app()->getRequest()->getPost('card_number_bcash');
        $month_bcash = Mage::app()->getRequest()->getPost('month_bcash');
        $year_bcash = Mage::app()->getRequest()->getPost('year_bcash');
        $name_card_bcash = Mage::app()->getRequest()->getPost('name_card_bcash');
        $cvv_bcash = Mage::app()->getRequest()->getPost('cvv_bcash');
        $installments_bcash = Mage::app()->getRequest()->getPost('installments_bcash');
        $cpf_cnpj_bcash = Mage::app()->getRequest()->getPost('cpf_cnpj_bcash');
        $ddd_bcash = Mage::app()->getRequest()->getPost('ddd_bcash');
        $phone_bcash = Mage::app()->getRequest()->getPost('phone_bcash');
        $orderId = $payment->getOrder()->getId();

        $installments_bcash = Mage::app()->getRequest()->getPost('installments_bcash');
        $cpf_cnpj_bcash = Mage::app()->getRequest()->getPost('cpf_cnpj_bcash');
        $ddd_bcash = Mage::app()->getRequest()->getPost('ddd_bcash');
        $phone_bcash = Mage::app()->getRequest()->getPost('phone_bcash');

        // initialize your gateway transaction as needed
        // $gateway is just an imaginary example of course
        Mage::throwException("Hadouken\n" . print_r($_POST, true));
        return $this;
    }


    public function createAddress()
    {
        $address  = $this->quote->getShippingAddress();
        $custName = $address->getName();
        $custAddr = $address->getStreetFull();
        $region   = $address->getRegion();
        $city     = $address->getCity();
        $country  = $address->getCountry();
        $postcode = $address->getPostCode();

        $address = new Address();
        $address->setAddress($custName);
        $address->setNumber('');
        $address->setComplement('');
        $address->setNeighborhood('');
        $address->setCity($city);
        $address->setState($region);
        $address->setZipCode($postcode);
        return $address;
    }

    public function createBuyer()
    {
        $customer_id = $this->quote->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($customer_id);
        $customerData = $customer->getData();
        $cpf_cnpj_bcash = Mage::app()->getRequest()->getPost('cpf_cnpj_bcash');
        $buyer = new Customer();
        $buyer->setMail($customerData['email']);
        $buyer->setName($customerData['name']);
        $buyer->setCpf($cpf_cnpj_bcash);
        $ddd_bcash = Mage::app()->getRequest()->getPost('ddd_bcash');
        $phone_bcash = Mage::app()->getRequest()->getPost('phone_bcash');
        $buyer->setPhone($ddd_bcash . $phone_bcash);
        $buyer->setAddress($this->createAddress());
        return $buyer;
    }

    public function createProduct()
    {
        $products = array();
        foreach ($this->items as $item) {
            $product = new Product();
            $product->setCode($item->getSku() ? $item->getSku() : $item->getId());
            $product->setDescription($item->getName());
            $product->setAmount($item->getQty());
            $product->setValue($item->getPrice());
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

    function setAdditionAndDiscont($addition = null, $discount = null)
    {
        if ($addition)
            $this->transactionRequest->setAddition($addition);
        if ($discount)
            $this->transactionRequest->setDiscount($discount);
    }

    function setShipping()
    {
        $shippingCost = $this->quote->getShippingAmount();
        $this->transactionRequest->setShipping($shippingCost);
        $shippingDescription = $this->quote->getShippingDescription();
        $this->transactionRequest->setShippingType($shippingDescription);
    }

    function createCreditCard()
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

    function createDependentTransactions()
    {
        $deps = array();
        foreach ($this->dependents as $dep) {
            $dep1 = new DependentTransaction();
            $dep1->setEmail("dep1@email.com");
            $dep1->setValue("0.50");
        }
        return $deps;
    }

}