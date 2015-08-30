<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php");

use Bcash\Service\Installments;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

/**
 * Class Bcash_Pagamento_Block_Form_Payment
 */
class Bcash_Pagamento_Block_Form_Payment extends Mage_Payment_Block_Form
{
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
            $quote = Mage::getModel("sales/quote")->load($quoteId);
            $grandTotal = floatval($quote->getData('grand_total'));
            $ignoreScheduledDiscount = false;

            if($this->sandbox){
                $installments->enableSandBox(true);
            }

            $response = $installments->calculate($grandTotal, $this->max_installments, $ignoreScheduledDiscount);

            echo "<pre>";
            var_dump($response);
            echo "</pre>";

            return array("ok" => true, "installments" => array("1" => $grandTotal));

        } catch (ValidationException $e) {

            echo "ErroTeste: " . $e->getMessage() . "\n";
            echo "<pre>";
            var_dump($e->getErrors());
            echo "</pre>";

            return array("ok" => false, "installments" => array("1" => $grandTotal));

        } catch (ConnectionException $e) {
            echo "ErroTeste: " . $e->getMessage() . "\n";
            echo "<pre>";
            var_dump($e->getErrors());
            echo "</pre>";

            return array("ok" => false, "installments" => array("1" => $grandTotal));
        }
    }
}
