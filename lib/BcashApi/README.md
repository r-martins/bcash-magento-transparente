# Bcash PHP SDK

[![Total Downloads](https://img.shields.io/packagist/dt/bcash/bcash-php-sdk.svg?style=flat)](https://packagist.org/packages/bcash/bcash-php-sdk)
[![GitHub tag](https://img.shields.io/github/tag/payu-br/bcash-php-sdk.svg)](https://github.com/payu-br/bcash-php-sdk)



## Exemplo de criação de transação
```php
<?php

require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Domain\Address;
use Bcash\Domain\Customer;
use Bcash\Domain\StateEnum;
use Bcash\Domain\PaymentMethod;
use Bcash\Domain\PaymentMethodEnum;
use Bcash\Domain\Product;
use Bcash\Domain\TransactionRequest;
use Bcash\Domain\ShippingTypeEnum;
use Bcash\Domain\CurrencyEnum;
use Bcash\Service\Payment;
use Bcash\Exception\ConnectionException;
use Bcash\Exception\ValidationException;


function createAddress()
{
	$address = new Address();
	$address->setAddress("Av. Tiradentes");
	$address->setNumber("123");
	$address->setComplement("Ap. 203");
	$address->setNeighborhood("Centro");
	$address->setCity("São Paulo");
	$address->setState(StateEnum::SAO_PAULO);
	$address->setZipCode("17500000");
	return $address;
}

function createBuyer()
{
	$buyer = new Customer();
	$buyer->setMail("comprador@comprador.com");
	$buyer->setName("Comprador Teste");
	$buyer->setCpf("850-822-365-04");
	$buyer->setPhone("34-3311-9999");
	$buyer->setAddress(createAddress());
	return $buyer;
}

function createProduct()
{
	//Product 1
	$product1 = new Product();
	$product1->setCode(1);
	$product1->setDescription("Produto de teste 1");
	$product1->setAmount(2);
	$product1->setValue(5.5);
	//Product 2
	$product2 = new Product();
	$product2->setCode(2);
	$product2->setDescription("Produto de teste 2");
	$product2->setAmount(1);
	$product2->setValue(9);
	//Product Array
	$products = array($product1, $product2);
	return $products;
}

function createTransactionRequest()
{
	$transactionRequest = new TransactionRequest();
	$transactionRequest->setSellerMail("lojamodelo@pagamentodigital.com.br");
	$transactionRequest->setOrderId("123456");
	$transactionRequest->setBuyer(createBuyer());
	$transactionRequest->setShipping(10.95);
	$transactionRequest->setShippingType(ShippingTypeEnum::E_SEDEX);
	$transactionRequest->setDiscount(1.20);
	$transactionRequest->setAddition(3);
	$transactionRequest->setPaymentMethod(PaymentMethodEnum::BANK_SLIP);
	$transactionRequest->setUrlReturn("https://www.bcash.com.br/loja/retorno.php");
	$transactionRequest->setUrlNotification("https://www.bcash.com.br/loja/aviso.php");
	$transactionRequest->setProducts(createProduct());
	$transactionRequest->setAcceptedContract("S");
	$transactionRequest->setViewedContract("S");

	return $transactionRequest;
}

$transactionRequest = createTransactionRequest();

$payment = new Payment("SUA CONSUMER KEY");

try {
	$response = $payment->create($transactionRequest);
	echo "<pre>";
	var_dump($response);die;
	echo "</pre>";

} catch (ValidationException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}

```

## Cartão de crédito
```php
use Bcash\Domain\Credicard;

/* ... */

function createCreditCard()
{
	$creditCard = new CreditCard();
	$creditCard->setHolder("Pedro D. F. Silva");
	$creditCard->setNumber("4111111111111111");
	$creditCard->setSecurityCode("123");
	$creditCard->setMaturityMonth("01");
	$creditCard->setMaturityYear("2016");
	return $creditCard;
}

$transactionRequest->setPaymentMethod(PaymentMethodEnum::VISA);
$transactionRequest->setCreditCard(createCreditCard());
```

## Adicionando transações dependentes (Comissionamento)

```php

/* ... */

function createDependentTransactions()
{
	$dep1 = new DependentTransaction();
	$dep1->setEmail("dep1@email.com");
	$dep1->setValue("1.00");

	$dep2 = new DependentTransaction();
	$dep2->setEmail("dep2@email.com");
	$dep2->setValue("1.95");

	$deps = array($dep1, $dep2); 
	return $deps;	
}

/* ... */

$transactionRequest->setDependentTransactions(createDependentTransactions());

```

## Consulta de conta
```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Service\Account;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

$email = "email@loja.com.br";
$token = "SEU TOKEN";

$account = new Account($email, $token);

try {
    $cpf = '00201208008';
	$response = $account->searchBy($cpf);
	echo "<pre>";
	var_dump($response);die;
	echo "</pre>";

} catch (ValidationException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}
```

## Consulta de parcelamento
```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Service\Installments;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

$email = "email@loja.com.br";
$token = "SEU TOKEN";

$installments = new Installments($email, $token);

try {
	$amount = 100.00; //Valor para cálculo
	$maxInstallments = 2; //Quantidade máxima de parcelas
	$ignoreScheduledDiscount = false; //Ignorar descontos programados
 	$response = $installments->calculate($amount, $maxInstallments, $ignoreScheduledDiscount);

	echo "<pre>";
	var_dump($response);die;
	echo "</pre>";

} catch (ValidationException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}

```

## Cancelamento de transação
```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Service\Cancellation;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

$email = "email@loja.com.br";
$token = "SEU TOKEN";

$cancellation = new Cancellation($email, $token);

try {
    $transactionId = 186148; // Id da transacao bcash a ser cancelada
    $response = $cancellation->execute($transactionId);
	echo "<pre>";
	var_dump($response);die;
	echo "</pre>";

} catch (ValidationException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}

```

=======

## Consulta de transação
```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Service\Consultation;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

$email = "email@loja.com.br";
$token = "SEU TOKEN";

$consultation = new Consultation($email, $token);

try {
	//Consulta pelo id da transação
	$transactionId = 999999; // id bcash da transacao a ser consultada
	$response = $consultation->searchByTransaction($transactionId);
	//OU
	//Consulta pelo id do pedido
	$orderId = "my-store-123456"; // id da sua loja enviado na criação da transação
	$response = $consultation->searchByOrder($orderid);
	
	echo "<pre>";
	var_dump($response);die;
	echo "</pre>";

} catch (ValidationException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}

```

## Notificação (Atualizando status da transação na sua loja)

O Bcash realizará as notificações na URL informada durante a criação da 
transação:
```php
$transactionRequest->setUrlNotification("https://www.minhaloja.com.br/notification.php");
```
O seguinte exemplo demonstra como deve ser realizado o recebimento
do post de retorno na url informada.

```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Service\Notification;
use Bcash\Domain\NotificationContent;
use Bcash\Domain\NotificationStatusEnum;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

/* Dados do post enviado pelo Bcash */
$transactionId = $_POST['transacao_id'];
$orderId = $_POST['pedido'];
$statusId = $_POST['status_id'];
$status = $_POST['status'];

$notificationContent = new NotificationContent($transactionId, $orderId, $statusId);

$email = "email@loja.com.br";
$token = "SEU TOKEN";

$notification = new Notification($email, $token, $notificationContent);

try {
	/* valor dos produtos + frete + acrecimo - desconto */
	$transactionValue = 273.20;
	$result = $notification->verify($transactionValue);

} catch (ValidationException $e) {
	$log->write("ErroTeste: " . $e->getMessage());
	$log->write($e->getErrors());

} catch (ConnectionException $e) {
	$log->write("ErroTeste: " . $e->getMessage());
	$log->write($e->getErrors());
}

if ($result == true) {
	$log->write('Notificação legitima');

	//ATUALIZAR STATUS NA LOJA
	if ($statusId == NotificationStatusEnum::APPROVED) {
	   // Liberar transação
	} else if ($statusId == NotificationStatusEnum::CANCELLED) {
	  // Cancelar transação
	}

	/* Verificar outros status na classe Bcash\Domain\NotificationStatusEnum */

} else {
	$log->write('Notificação ilegitima');
}

```

## Simulando a notificação
```php
require_once '../lib/bcash-php-sdk/autoloader.php';

use Bcash\Test\NotificationSimulator;
use Bcash\Exception\ConnectionException;

$notificationUrl = "https://hostofstore.com/address/alert";
$transactionId = 987654321;  // id transacao do bcash
$orderId = "my-store-1234"; // id pedido da sua loja
$statusId = 3; // Aprovada

try {
	$result = NotificationSimulator::test ($notificationUrl, $transactionId, $orderId, $statusId);

	echo "<pre>";
	var_dump($result);die;
	echo "</pre>";

} catch (ConnectionException $e) {
	echo "ErroTeste: " . $e->getMessage() . "\n";
	echo "<pre>";
	var_dump($e->getErrors());die;
	echo "</pre>";
}

```

## Usando o ambiente de testes
```php

/* ... */
$payment->enableSandBox(true);
$account->enableSandBox(true);
$installments->enableSandBox(true);
$cancellation->enableSandBox(true);
$consultation->enableSandBox(true);
$notification->enableSandBox(true);
/* ... */

```
