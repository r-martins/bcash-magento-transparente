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
    private $subTotal;
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
        $this->dependents = $this->getConfigData('transacao_dependente');
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
        $this->grandTotal = floatval($this->quote->getData('grand_total'));
        $this->subTotal = floatval($this->quote->getSubtotal());
        $shippingHandling = floatval($this->grandTotal -$this->subTotal);
        //Set required items.
        $this->billingData = $this->quote->getBillingAddress()->getData();
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
        $address = $this->quote->getShippingAddress();

        $street      = $address->getStreet(1);
        $numero      = $address->getStreet(2);
        $complemento = $address->getStreet(3);
        $bairro      = $address->getStreet(4);

        $addressObj = new Address();
        $addressObj->setAddress($street);
        $addressObj->setNumber($numero ? $numero : 'SN');
        $addressObj->setComplement($complemento);
        $addressObj->setNeighborhood($bairro);
        $addressObj->setCity($address->getCity());
        $addressObj->setState($this->parseState($address->getRegion()));
        $addressObj->setZipCode($address->getPostcode());

        return $addressObj;
    }

    public function createBuyer()
    {
        $customer_id = $this->quote->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($customer_id);
        $customerData = $customer->getData();
        $cpf_cnpj_bcash = isset($customerData["taxvat"]) ? $customerData["taxvat"] : Mage::app()->getRequest()->getPost('cpf_cnpj_bcash');
        $buyer = new Customer();
        $buyer->setMail($customerData['email']);
        $name  = ($customerData['firstname']);
        $name .= isset($customerData['middlename']) ? ' ' . $customerData['middlename'] : '';
        $name .= isset($customerData['lastname'])   ? ' ' . $customerData['lastname']   : '';
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
        $unserialezedDeps = unserialize($this->dependents);
        foreach ($unserialezedDeps['dependente'] as $key => $obj ) {
            if($obj && isset($unserialezedDeps['percentual'][$key]) && $unserialezedDeps['percentual'][$key] > 0 ) {
                $dependent = new DependentTransaction();
                $dependent->setEmail($obj);
                $value = ( $this->subTotal / 100 ) * floatval($unserialezedDeps['percentual'][$key]);
                $dependent->setValue(floatval($value));
                array_push($deps, $dependent);
            }
        }
        return $deps;
    }


    /**
     * Replace language-specific characters by ASCII-equivalents.
     * @see http://stackoverflow.com/a/16427125/529403
     * @param string $s
     * @return string
     */
    public static function normalizeChars($s) {
        $replace = array(
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'Ae', 'Å'=>'A', 'Æ'=>'A', 'Ă'=>'A',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'ae', 'å'=>'a', 'ă'=>'a', 'æ'=>'ae',
            'þ'=>'b', 'Þ'=>'B',
            'Ç'=>'C', 'ç'=>'c',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'Ğ'=>'G', 'ğ'=>'g',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'ı'=>'i', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'Ñ'=>'N',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe', 'Ø'=>'O', 'ö'=>'oe', 'ø'=>'o',
            'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'Š'=>'S', 'š'=>'s', 'Ş'=>'S', 'ș'=>'s', 'Ș'=>'S', 'ş'=>'s', 'ß'=>'ss',
            'ț'=>'t', 'Ț'=>'T',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'Ue',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'ue',
            'Ý'=>'Y',
            'ý'=>'y', 'ý'=>'y', 'ÿ'=>'y',
            'Ž'=>'Z', 'ž'=>'z'
        );
        return strtr($s, $replace);
    }


    public function parseState($state)
    {
        if (strlen($state) == 2 && is_string($state)) {
            return strtoupper($state);
        } else if (strlen($state) > 2 && is_string($state)) {
            $state = $this->normalizeChars($state);
            $state = trim($state);
            $state = strtoupper($state);
            $codes = array("AC" => "ACRE", "AL" => "ALAGOAS", "AM" => "AMAZONAS", "AP" => "AMAPA", "BA" => "BAHIA", "CE" => "CEARA", "DF" => "DISTRITO FEDERAL", "ES" => "ESPIRITO SANTO", "GO" => "GOIAS", "MA" => "MARANHAO", "MT" => "MATO GROSSO", "MS" => "MATO GROSSO DO SUL", "MG" => "MINAS GERAIS", "PA" => "PARA", "PB" => "PARAIBA", "PR" => "PARANA", "PE" => "PERNAMBUCO", "PI" => "PIAUI", "RJ" => "RIO DE JANEIRO", "RN" => "RIO GRANDE DO NORTE", "RO" => "RONDONIA", "RS" => "RIO GRANDE DO SUL", "RR" => "RORAIMA", "SC" => "SANTA CATARINA", "SE" => "SERGIPE", "SP" => "SAO PAULO", "TO" => "TOCANTINS");
            if ($code = array_search($state, $codes)) {
                return $code;
            }
        }
        return $state;
    }
}
