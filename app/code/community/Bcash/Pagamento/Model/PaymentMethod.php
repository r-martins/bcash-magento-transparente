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
     * @var bool
     */
    protected $_isGateway = true;
    /**
     * @var bool
     */
    protected $_canCapture = true;
    /**
     * @var bool
     */
    protected $_canAuthorize = true;
    /**
     * @var bool
     */
    protected $_canOrder = true;
    /**
     * @var string
     */
    protected $_formBlockType = 'pagamento/form_payment';

    //Flag executa o método initalize() com o checkout completo.
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    //Variaveis de Transação
    /**
     * @var
     */
    private $email;
    /**
     * @var
     */
    private $consumer_key;
    /**
     * @var
     */
    private $sandbox;
    /**
     * @var
     */
    private $items;
    /**
     * @var
     */
    private $billingData;
    /**
     * @var
     */
    private $grandTotal;
    /**
     * @var
     */
    private $subTotal;
    /**
     * @var
     */
    private $transactionRequest;
    /**
     * @var
     */
    private $quoteIdTransaction;
    /**
     * @var
     */
    private $dependents;
    /**
     * @var
     */
    private $quoteBcash;
    /**
     * @var
     */
    private $discountBcash;

    /**
     * Retornar URL para redirecionar o cliente.
     * Chamado depois que o botão é clicado.
     * Chamado após a criação e registro do pedido "Order".
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        /* Mage log is your friend.
         * While it shouldn't be on in production,
         * it makes debugging problems with your api much easier.
         * The file is in magento-root/var/log/system.log
         */
        Mage::log('Called custom ' . __METHOD__);
        $url = $this->getConfigData('redirecturl');
        return $url;
    }

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
        $this->email = $this->getConfigData('email');
        $this->consumer_key = $this->getConfigData('consumer_key');
        $this->dependents = $this->getConfigData('transacao_dependente');
        $this->sandbox = $this->getConfigData('sandbox');
        parent::initialize($paymentAction, $stateObject);
        if ($paymentAction != 'sale') {
            return $this;
        }
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; // state now = 'pending_payment'
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
        $sessionCheckout = Mage::getSingleton('checkout/session');
        $quoteId = $sessionCheckout->getQuoteId();
        $sessionCheckout->setData('QuoteId', $quoteId);
        $this->quoteBcash = Mage::getModel("sales/quote")->load($quoteId);
        $this->grandTotal = floatval($this->quoteBcash->getData('grand_total'));
        $this->subTotal = floatval($this->quoteBcash->getSubtotal());
        $shippingHandling = floatval($this->grandTotal -$this->subTotal);
        $this->billingData = $this->quoteBcash->getBillingAddress()->getData();
        $this->quoteIdTransaction = (str_pad($quoteId, 9, 0, STR_PAD_LEFT));
        $this->items = $this->quoteBcash->getItemsCollection()->getItems();
        $this->transactionRequest = $this->createTransactionRequest();
        $this->setShipping();
        $this->setPaymentMethod();
        $payment = new Payment($this->consumer_key);
        if ($this->sandbox) {
            $payment->enableSandBox(true);
        }
        try {
            $response = $payment->create($this->transactionRequest);
            //TODO: Tratar retorno
            $responseTransaction = $response;
            //$response['transactionId'];//224
            //$response['orderId'];//000000700
            //$response['status'];//1
            //$response['descriptionStatus'];//Em+andamento
            //$response['paymentLink'];//https%3A%2F%2Fsandbox.bcash.com.br%2Fcheckout%2FBoleto%2FImprime%2F224%2F0z0ajEHp0RqdnYydaRlPFkCME2cuwt

            ///* @var $order Mage_Sales_Model_Order */
            //$comment = 'blah blah';
            //$order->addStatusHistoryComment($comment);
            //$order->save();

            //TODO: $stateObject IF Approved (CARTAO E TEF) outros aprovam depois


        } catch (ValidationException $e) {
            $errorsArr = $e->getErrors();
            $errorsList = $errorsArr->list;
            $messages  = array();
            foreach ($errorsList as $err) {
                $messages[] = $err->code . " - " . urldecode($err->description);
            }
            Mage::throwException(implode("\n", $messages));
        } catch (ConnectionException $e) {
            $errorsArr = $e->getErrors();
            $errorsList = $errorsArr->list;
            $messages  = array();
            foreach ($errorsList as $err) {
                $messages[] = $err->code . " - " . urldecode($err->description);
            }
            Mage::throwException(implode("\n", $messages));
        }
    }

    /**
     * Cria o objeto TransactionRequest via SDK Api Bcash.
     * @return TransactionRequest
     */
    public function createTransactionRequest()
    {
        $url = Mage::getUrl('pagamento/notification/request');
        $transactionRequest = new TransactionRequest();
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

    /**
     * Adiciona o método de pagamento a transação atual.
     */
    public function setPaymentMethod()
    {
        $cards  = array(PaymentMethodEnum::VISA, PaymentMethodEnum::MASTERCARD, PaymentMethodEnum::AMERICAN_EXPRESS, PaymentMethodEnum::AURA, PaymentMethodEnum::DINERS, PaymentMethodEnum::HIPERCARD, PaymentMethodEnum::ELO);
        $boleto = PaymentMethodEnum::BANK_SLIP;
        $tefs   = array(PaymentMethodEnum::BB_ONLINE_TRANSFER, PaymentMethodEnum::BRADESCO_ONLINE_TRANSFER, PaymentMethodEnum::ITAU_ONLINE_TRANSFER, PaymentMethodEnum::BANRISUL_ONLINE_TRANSFER, PaymentMethodEnum::HSBC_ONLINE_TRANSFER);
        $payment_method = Mage::app()->getRequest()->getPost('payment-method');
        $installments = Mage::app()->getRequest()->getPost('installments_bcash');
        $installments = $installments ?:1;

        $this->transactionRequest->setPaymentMethod($payment_method);
        if (in_array($payment_method, $cards)) {
            $this->transactionRequest->setCreditCard($this->createCreditCard());
            $this->transactionRequest->setInstallments($installments);
        }

        if ($installments == 1) {
            if (in_array($payment_method, $cards)) {
                $percent = $this->getConfigData('desconto_credito_1x');
            } elseif (in_array($payment_method, $tefs)) {
                $percent = $this->getConfigData('desconto_tef');
            } else {
                $percent = $this->getConfigData('desconto_boleto');
            }
            if ($percent) {
                $discount = floatval(($this->subTotal / 100) * $percent);
                $this->setDiscount($discount);
                //TODO: Adicionar Desconto ao Pedido do Magento.

            }
        }
    }

    /**
     * Adiciona o endereço a transação atual.
     * @return Address
     */
    public function createAddress()
    {
        $address = $this->quoteBcash->getShippingAddress();
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

    /**
     * Adiciona o comprador a transação atual
     * @return Customer
     */
    public function createBuyer()
    {
        $customer_id = $this->quoteBcash->getCustomerId();
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

    /**
     * Adiciona o telefone a transação atual.
     * @return string
     */
    public function completePhone()
    {
        $address  = $this->quoteBcash->getBillingAddress()->getData();
        $ddd_bcash = Mage::app()->getRequest()->getPost('ddd_bcash');
        $phone_bcash = Mage::app()->getRequest()->getPost('phone_bcash');
        $full_phone = "";
        if (!$address['telephone'] && $ddd_bcash && $full_phone) {
            $full_phone = $ddd_bcash . $full_phone;
        }
        return $this->parsePhone($full_phone);
    }

    /**
     * Retorna somente 11 dígitos do telefone.
     * @param $phone
     * @return string
     */
    public function parsePhone($phone)
    {
        $phone = preg_replace('/[^0-9]+/', '', $phone);
        if (strlen($phone) > 11) {
            return substr($phone, -11);
        }
        return $phone;
    }

    /**
     * Adiciona os produtos do carrinho de compras a transação atual.
     * @return array
     */
    public function createProduct()
    {
        $products = array();
        foreach ($this->items as $item) {
            $price = $item->getPrice();
            if($price > 0){
                $product = new Product();
                $cod = $item->getSku() ? $item->getSku() : $item->getId();
                $product->setCode($cod);
                $name = $item->getName();
                $product->setDescription($name);
                $qty = $item->getQty();
                $product->setAmount(intval($qty));
                $product->setValue(floatval($price));
                array_push($products, $product);
            }
        }
        return $products;
    }

    /**
     * Adiciona valor adicional a transação atual.
     * @param null $addition
     * @param null $discount
     */
    public function setAddition($addition = 0)
    {
        $this->transactionRequest->setAddition($addition);
    }

    /**
     * Adiciona valor de desconto a transação atual caso o mesmo esteja definido no módulo.
     * @param null $addition
     * @param null $discount
     */
    public function setDiscount($discount)
    {
        $this->discountBcash = $discount;
        $this->transactionRequest->setDiscount($discount);
    }

    /**
     * Adiciona o tipo de Frete e valor definido para o mesmo a transação atual.
     * @return void
     */
    public function setShipping()
    {
        $shipping = $this->quoteBcash->getShippingAddress()->getData();
        $this->transactionRequest->setShipping(floatval($shipping['shipping_amount']));
        $this->transactionRequest->setShippingType($shipping['shipping_description']);
    }

    /**
     * Adiciona o cartão de crédito a transação atual quando solicitado a transação atual.
     * @return CreditCard
     */
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

    /**
     * Adiciona as transações dependentes a transação atual.
     * @return array
     */
    public function createDependentTransactions()
    {
        $deps = array();
        $unserialezedDeps = unserialize($this->dependents);
        foreach ($unserialezedDeps['dependente'] as $key => $obj) {
            if ($obj && isset($unserialezedDeps['percentual'][$key]) && $unserialezedDeps['percentual'][$key] > 0) {
                $dependent = new DependentTransaction();
                $dependent->setEmail($obj);
                $value = ($this->subTotal / 100) * floatval($unserialezedDeps['percentual'][$key]);
                $dependent->setValue(floatval(number_format($value,2,'.','')));
                array_push($deps, $dependent);
            }
        }
        return $deps;
    }

    /**
     * Substitui os caracteres especificos da linguagem por caracteres ASCII equivalentes.
     * @see http://stackoverflow.com/a/16427125/529403
     * @param string $s
     * @return string
     */
    public static function normalizeChars($s)
    {
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

    /**
     * Realiza a identificação do Estado preenchido pelo usuário.
     * @param $state
     * @return mixed|string
     */
    public function parseState($state)
    {
        if (strlen($state) == 2 && is_string($state)) {
            return strtoupper($state);
        } elseif (strlen($state) > 2 && is_string($state)) {
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
