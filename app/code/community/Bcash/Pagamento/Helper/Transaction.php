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
use Bcash\Service\Payment;
use Bcash\Domain\DependentTransaction;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

class Bcash_Pagamento_Helper_Transaction extends Mage_Payment_Helper_Data
{
    private $email;
    private $token;
    private $obj;
    private $sandbox;
    private $dependents;
    private $consumer_key;

    //Variaveis de Transação
    /**
     * @var
     */
    private $itemsBcash;
    /**
     * @var
     */
    private $billingDataBcash;
    /**
     * @var
     */
    private $grandTotalBcash;
    /**
     * @var
     */
    private $subTotalBcash;
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
    private $dependentsBcash;
    /**
     * @var
     */
    private $quoteBcash;
    /**
     * @var
     */
    private $discountBcash;
    /**
     * @var
     */
    private $discountPercentBcash;

    /**
     * @var
     */
    private $deps = array();

    /**
     * @var
     */
    private $installments;

    /**
     * @var
     */
    private $payment_method;


    public function __construct()
    {
        $this->obj = Mage::getSingleton('Bcash_Pagamento_Model_PaymentMethod');
        $this->email = $this->obj->getConfigData('email');
        $this->token = $this->obj->getConfigData('token');
        $this->sandbox = $this->obj->getConfigData('sandbox');
        $this->consumer_key = $this->obj->getConfigData('consumer_key');
        $this->dependents = $this->obj->getConfigData('transacao_dependente');
    }

    public function startTransaction(){
        $sessionCheckout = Mage::getSingleton('checkout/session');
        $quoteId = $sessionCheckout->getQuoteId();
        $sessionCheckout->setData('QuoteIdBcash', $quoteId);
        $this->quoteBcash = Mage::getModel("sales/quote")->load($quoteId);
        $this->grandTotalBcash = floatval($this->quoteBcash->getData('grand_total'));
        $this->subTotalBcash = floatval($this->quoteBcash->getSubtotal());
        //$shippingHandling = floatval($this->grandTotalBcash -$this->subTotalBcash);
        $this->billingDataBcash = $this->quoteBcash->getBillingAddress()->getData();
        $this->quoteIdTransaction = (str_pad($quoteId, 9, 0, STR_PAD_LEFT));
        $this->itemsBcash = $this->quoteBcash->getItemsCollection()->getItems();
        $this->transactionRequest = $this->createTransactionRequestBcash();
        $this->setShippingBcash();
        $this->setPaymentMethodBcash();
        $payment = new Payment($this->consumer_key);
        if ($this->sandbox) {
            $payment->enableSandBox(true);
        }
        try {
            $response = $payment->create($this->transactionRequest);
            $payment_method = Mage::app()->getRequest()->getPost('payment-method');

            return array(
                'response' => $response,
                'payment_method' => $payment_method,
                'discountPercent' => $this->discountPercentBcash,
                'discount' => $this->discountBcash,
                'deps' => $this->deps,
                'installments' => $this->installments
            );
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
    public function createTransactionRequestBcash()
    {
        $url = Mage::getUrl('pagamento/notification/request');
        $transactionRequest = new TransactionRequest();
        $transactionRequest->setSellerMail($this->email);
        $transactionRequest->setOrderId($this->quoteIdTransaction);
        $transactionRequest->setBuyer($this->createBuyerBcash());
        $transactionRequest->setUrlNotification($url);
        $transactionRequest->setProducts($this->createProductBcash());
        $transactionRequest->setAcceptedContract("S");
        $transactionRequest->setViewedContract("S");
        $transactionRequest->setDependentTransactions($this->createDependentTransactionsBcash());
        return $transactionRequest;
    }

    /**
     * Adiciona o método de pagamento a transação atual.
     */
    public function setPaymentMethodBcash()
    {
        $cards  = array(PaymentMethodEnum::VISA, PaymentMethodEnum::MASTERCARD, PaymentMethodEnum::AMERICAN_EXPRESS, PaymentMethodEnum::AURA, PaymentMethodEnum::DINERS, PaymentMethodEnum::HIPERCARD, PaymentMethodEnum::ELO);
        $boleto = PaymentMethodEnum::BANK_SLIP;
        $tefs   = array(PaymentMethodEnum::BB_ONLINE_TRANSFER, PaymentMethodEnum::BRADESCO_ONLINE_TRANSFER, PaymentMethodEnum::ITAU_ONLINE_TRANSFER, PaymentMethodEnum::BANRISUL_ONLINE_TRANSFER, PaymentMethodEnum::HSBC_ONLINE_TRANSFER);
        $payment_method = Mage::app()->getRequest()->getPost('payment-method');
        $installments = Mage::app()->getRequest()->getPost('installments_bcash');
        $this->installments = $installments ?:1;
        $this->payment_method = $payment_method;

        $this->transactionRequest->setPaymentMethod($this->payment_method);
        if (in_array($this->payment_method, $cards)) {
            $this->transactionRequest->setCreditCard($this->createCreditCardBcash());
            $this->transactionRequest->setInstallments($this->installments);
        }

        if ($installments == 1) {
            if (in_array($this->payment_method, $cards)) {
                $percent = $this->obj->getConfigData('desconto_credito_1x');
            } elseif (in_array($this->payment_method, $tefs)) {
                $percent = $this->obj->getConfigData('desconto_tef');
            } else {
                $percent = $this->obj->getConfigData('desconto_boleto');
            }
            if ($percent) {
                $discount = floatval(($this->subTotalBcash / 100) * $percent);
                $this->discountPercentBcash = $percent;
                $this->discountBcash = $discount;
                $this->setDiscountBcash($discount);
                //TODO: Adicionar Desconto ao Pedido do Magento.

            }
        }
    }

    /**
     * Adiciona o endereço a transação atual.
     * @return Address
     */
    public function createAddressBcash()
    {
        $address     = $this->quoteBcash->getShippingAddress();
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
        $addressObj->setState($this->parseRegionBcash($address->getRegion()));
        $addressObj->setZipCode($address->getPostcode());
        return $addressObj;
    }

    /**
     * Adiciona o comprador a transação atual
     * @return Customer
     */
    public function createBuyerBcash()
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
        $buyer->setPhone($this->completePhoneBcash());
        $buyer->setAddress($this->createAddressBcash());
        return $buyer;
    }

    /**
     * Adiciona o telefone a transação atual.
     * @return string
     */
    public function completePhoneBcash()
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
    public function createProductBcash()
    {
        $products = array();
        foreach ($this->itemsBcash as $item) {
            $price = $item->getPrice();
            if ($price > 0) {
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
    public function setAdditionBcash($addition = 0)
    {
        $this->transactionRequest->setAddition($addition);
    }

    /**
     * Adiciona valor de desconto a transação atual caso o mesmo esteja definido no módulo.
     * @param null $addition
     * @param null $discount
     */
    public function setDiscountBcash($discount)
    {
        $this->discountBcash = $discount;
        $this->transactionRequest->setDiscount($discount);
    }

    /**
     * Adiciona o tipo de Frete e valor definido para o mesmo a transação atual.
     * @return void
     */
    public function setShippingBcash()
    {
        $shipping = $this->quoteBcash->getShippingAddress()->getData();
        $this->transactionRequest->setShipping(floatval($shipping['shipping_amount']));
        $this->transactionRequest->setShippingType($shipping['shipping_description']);
    }

    /**
     * Adiciona o cartão de crédito a transação atual quando solicitado a transação atual.
     * @return CreditCard
     */
    public function createCreditCardBcash()
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
    public function createDependentTransactionsBcash()
    {
        $unserialezedDeps = unserialize($this->dependentsBcash);
        foreach ($unserialezedDeps['dependente'] as $key => $obj) {
            if ($obj && isset($unserialezedDeps['percentual'][$key]) && $unserialezedDeps['percentual'][$key] > 0) {
                $dependent = new DependentTransaction();
                $dependent->setEmail($obj);
                $value = ($this->subTotalBcash / 100) * floatval($unserialezedDeps['percentual'][$key]);
                $dependent->setValue(floatval(number_format($value, 2, '.', '')));
                array_push($this->deps, $dependent);
            }
        }
        return $this->deps;
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
    public function parseRegionBcash($state)
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