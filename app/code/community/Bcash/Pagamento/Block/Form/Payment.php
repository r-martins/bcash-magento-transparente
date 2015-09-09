<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php");

use Bcash\Service\Installments;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;
use Bcash\Domain\PaymentMethodEnum;

/**
 * Class Bcash_Pagamento_Block_Form_Payment
 */
class Bcash_Pagamento_Block_Form_Payment extends Mage_Payment_Block_Form
{
    protected $_code = 'pagamento';

    /**
     * @var Mage_Core_Model_Abstract
     */
    private $obj;
    /**
     * @var
     */
    private $email;
    /**
     * @var
     */
    private $token;
    /**
     * @var
     */
    private $sandbox;
    /**
     * @var
     */
    private $max_installments;

    /**
     * @var
     */
    private $cards;
    /**
    * @var
    */
    private $boleto;
    /**
    * @var
    */
    private $tefs;
    /**
     * @var
     */
    private $desconto_credito_1x;
    /**
     * @var
     */
    private $quote;
    /**
     * @var
     */
    private $cpf;
    /**
     * @var
     */
    private $phone;

    /**
     * Instancia o template referente ao método de pagamento
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('pagamento/form/payment.phtml');
        $this->obj = Mage::getSingleton('Bcash_Pagamento_Model_PaymentMethod');
        $this->email   = $this->obj->getConfigData('email');
        $this->token   = $this->obj->getConfigData('token');
        $this->sandbox = $this->obj->getConfigData('sandbox');
        $this->max_installments = $this->obj->getConfigData('max_installments');

        $this->cpf = $this->obj->getConfigData('cpf');
        $this->phone = $this->obj->getConfigData('phone');

        $this->desconto_credito_1x = $this->obj->getConfigData('desconto_credito_1x');

        $this->cards  = array(PaymentMethodEnum::VISA, PaymentMethodEnum::MASTERCARD, PaymentMethodEnum::AMERICAN_EXPRESS, PaymentMethodEnum::AURA, PaymentMethodEnum::DINERS, PaymentMethodEnum::HIPERCARD, PaymentMethodEnum::ELO);
        $this->boleto = PaymentMethodEnum::BANK_SLIP;
        $this->tefs   = array(PaymentMethodEnum::BB_ONLINE_TRANSFER, PaymentMethodEnum::BRADESCO_ONLINE_TRANSFER, PaymentMethodEnum::ITAU_ONLINE_TRANSFER, PaymentMethodEnum::BANRISUL_ONLINE_TRANSFER, PaymentMethodEnum::HSBC_ONLINE_TRANSFER);
    }

    /**
     * Retorna os meios de pagamento disponíveis no módulo Bcash.
     * @return mixed
     */
    public function getPaymentMethods()
    {
        Mage::log("Bcash_Pagamento_Block_Form_Payment called getPaymentMethods");
        return Mage::helper('pagamento/paymentMethod')->getPaymentMethods();
    }

    /**
     * Retorna os parcelamentos possíveis via cartão de crédito.
     * @return array
     */
    public function getInstallments()
    {
        $installments = new Installments($this->email, $this->token);
        try {
            $sessionCheckout = Mage::getSingleton('checkout/session');
            $quoteId = $sessionCheckout->getQuoteId();
            $this->quote = Mage::getModel("sales/quote")->load($quoteId);
            $grandTotal = floatval($this->quote->getData('grand_total'));
            $ignoreScheduledDiscount = false;
            if($this->sandbox){
                $installments->enableSandBox(true);
            }
            $response = $installments->calculate($grandTotal, $this->max_installments, $ignoreScheduledDiscount);
            return array("ok" => true, "installments" => array(0 => $this->prepareInstallmentsCards($response)));
        } catch (ValidationException $e) {
            Mage::log("Erro Bcash ValidationException:" . implode(",", $e->getErrors()));
            return array("ok" => false, "installments" => array("1" => $grandTotal));
        } catch (ConnectionException $e) {
            Mage::log("Erro Bcash ConnectionException:" . implode(",", $e->getErrors()));
            return array("ok" => false, "installments" => array("1" => $grandTotal));
        }
    }

    public function prepareInstallmentsCards($installments)
    {
        foreach ($installments->paymentTypes as &$obj) {
            if ('card' != $obj->name) {
                unset($obj);
            } else {
                if ($this->desconto_credito_1x) {
                    $subTotal = floatval($this->quote->getSubtotal());
                    $desconto = ($this->desconto_credito_1x / 100) * $subTotal;
                    if ($desconto) {
                        foreach ($obj->paymentMethods as &$type) {
                            foreach ($type->installments as &$installment) {
                                if ($installment->number == 1) {
                                    $installment->installmentAmount -= $desconto;
                                    $installment->installmentAmountDesc = " ({$this->desconto_credito_1x} % de desconto)";
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $installments;
    }



}


