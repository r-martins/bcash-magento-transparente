<?php

namespace Bcash\Service;

use Bcash\Service\IEnvironmentManager;
use Bcash\Http\GetRequest;
use Bcash\Config\Config;
use Bcash\Http\Authentication\Basic;
use Bcash\Helper\HttpHelper;
use Bcash\Http\Connection;

/**
 * Cliente para consulta de parcelamento
 *
 */
class Installments implements IEnvironmentManager
{
	const route = "/seller/%s/installments";
	private $email;
	private $token;
	private $url;

	public function __construct($email, $token)
	{
		$this->email = $email;
		$this->token = $token;
		$this->url = Config::host . self::route;
	}
	
	/**
	 * Chama o serviço de cálculo de parcelas.
	 *
	 * @param amount
	 *           Valor para o cálculo.
     * @param max
	 *           Máximo de parcelas aceito.
	 * @return Objeto que contém as formas de parcelamento calculadas
	 * @throws InstallmentException
	 *             exceção em caso de na consulta.
	 */
	public function calculate($amount, $max, $id_vendedor)
	{
		$request = $this->generateRequest($amount, $max, $id_vendedor);
		$response = $this->send($request);
	
		return HttpHelper::fromJson($response->getContent());
	}
	
	private function generateRequest($amount, $max, $id_vendedor)
	{
		$request = new GetRequest($this->url);
	
		$basic = new Basic();
		$request->addHeader($basic->generateHeader($this->email, $this->token));
		$request->addHeader("Content-Type:application/x-www-form-urlencoded;charset=" . Config::charset);
		$request->addParam("amount", $amount);
		$request->addParam("maxInstallments", $max);
		
// 		$url = vsprintf($this->url, $this->email);
		$url = vsprintf($this->url, $id_vendedor);
		$url = $url . "?" . http_build_query($request->getParams(), '', '&'); 

		$request->setUrl($url);
		return $request;
	}
	
	private function send($request)
	{
		$connection = new Connection(Config::timeout);
		$response = $connection->get($request);
	
		return $response;
	}
	
	public function enableSandBox($bool)
	{
		$this->url = Config::host . self::route;
	
		if ($bool) {
			$this->url = Config::hostSandBox . self::route;
		}
	}
	
}
