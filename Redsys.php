<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Redsys extends CI_Model {

	private $version = "HMAC_SHA256_V1";
	private $content = array();
	private $key = NULL;

	function __construct(){
		parent::__construct();
	}

	function load_default(){
		$this->key($this->config->item('redsys_key'));
		$this->currency($this->config->item('redsys_currency'));
		$this->commerce($this->config->item('redsys_commerce'));
		$this->terminal($this->config->item('redsys_terminal'));
		$this->order(date("ymdHis"));

		return $this;
	}

	function parse(){

	}

	function reset($all = false){
		$this->content = array();
		if($all){ $this->key = NULL; }
		return $this;
	}

	// --------------------------------------------

	function key($data = NULL){
		if(!empty($data)){ $this->key = $data; }
		return $this;
	}

	function commerce($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_MerchantCode', $data);
	}

	function terminal($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Terminal', $data);
	}

	function currency($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Currency', $data);
	}

	function order($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Order', $data);
	}

	function data($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_MerchantData', $data);
	}

	function productDescription($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_ProductDescription', $data);
	}

	function clientName($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Titular', $data);
	}

	function module($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Module', $data);
	}

	// ----------------------------------------

	function merchantName($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_MerchantName', $data);
	}

	function merchantURL($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_MerchantURL', $data);
	}

	function merchantURLOK($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_UrlOK', $data);
	}

	function merchantURLKO($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_UrlKO', $data);
	}

	// -------------------------------------------

	function amount($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Amount', $data);
	}

	function amount_total($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_SumTotal', $data);
	}

	function transactionType($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_TransactionType', $data);

		/* 0 – Autorización
		1 – Preautorización
		2 – Confirmación de preautorización
		3 – Devolución Automática
		5 – Transacción Recurrente
		6 – Transacción Sucesiva
		7 – Pre-autenticación
		8 – Confirmación de pre-autenticación
		9 – Anulación de Preautorización
		O – Autorización en diferido
		P– Confirmación de autorización en diferido
		Q - Anulación de autorización en diferido
		R – Cuota inicial diferido
		S – Cuota sucesiva diferido */
	}

	function frecuency($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_DateFrecuency', $data);
	}

	function chargeExpiry($data = NULL){
		if(!empty($data)){ $data = date("Y-m-d", strtotime($data)); }
		return $this->__process_data_generic('Ds_Merchant_ChargeExpiryDate', $data);
	}

	function identifier($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_Identifier', $data);
	}

	function authCode($data = NULL){
		return $this->__process_data_generic('Ds_Merchant_AuthorisationCode', $data);
	}

	function direct_payment($data = NULL){
		if($data === NULL){ return $this->content['Ds_Merchant_DirectPayment']; }

		if($data){ $this->content['Ds_Merchant_DirectPayment'] = "true"; }
		elseif($data === FALSE){ $this->content['Ds_Merchant_DirectPayment'] = "false"; }
		else{ $this->content['Ds_Merchant_DirectPayment'] = "false"; }

		return $this;
	}

	// -------------------------------------

	function charge_pay($amount){
		$this->amount($amount);
		$this->transactionType('0');

		return $this;
	}

	function charge_split($total, $amount, $identifier = NULL, $mindays = 0, $expiry = NULL){
		if(empty($expiry)){ $expiry = date("Y-m-d", strtotime("+1 year")); }
		if(!is_numeric($mindays)){ $mindays = 0; }
		if($amount > $total){ $amount = $total; }

		$this->amount($amount);
		$this->amount_total($total);
		$this->frecuency($mindays);
		$this->chargeExpiry($expiry);
		if(empty($identifier)){
			$this->identifier('REQUIRED');
			$this->transactionType('5'); // Pago recurrente inicial
		}else{
			$this->identifier($identifier);
			// $this->transactionType('6'); // Pago recurrente sucesivo
			$this->transactionType('0');
			$this->direct_payment(true);
		}

		return $this;
	}

	// -----------------------------------------------

	function encrypt_3DES($message, $key){
		if(function_exists('openssl_encrypt')){
			$l = ceil(strlen($message) / 8) * 8;
			$message = $message.str_repeat("\0", $l - strlen($message));

			return substr(openssl_encrypt($message, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);
		}

		// Se establece un IV por defecto
		$bytes = array(0,0,0,0,0,0,0,0); //byte [] IV = {0, 0, 0, 0, 0, 0, 0, 0}
		$iv = implode(array_map("chr", $bytes)); //PHP 4 >= 4.0.2

		// Se cifra
		$ciphertext = mcrypt_encrypt(MCRYPT_3DES, $key, $message, MCRYPT_MODE_CBC, $iv); //PHP 4 >= 4.0.2
		return $ciphertext;
	}

	function mac256($msg,$key){
		if (PHP_VERSION_ID < 50102) {
			// $res = hash_hmac4('sha256', $msg, $key, true);
			return "";
		} else {
			$res = hash_hmac('sha256', $msg, $key, true);//(PHP 5 >= 5.1.2)
		}
		return $res;
	}

	function encode_special($day = NULL, $hour = NULL, $minute = NULL, $second = NULL){
		if(empty($day)){ $day = date("d"); }
		if(empty($hour)){ $hour = date("H"); }
		if(empty($minute)){ $minute = date("i"); }
		if(empty($second)){ $second = date("s"); }
		// Todo esto en 3 carácteres! :O
		$dic = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwyxz';
		return substr($dic, $day+$hour, 1) .substr($dic, $minute, 1) .substr($dic, $second, 1);
	}

	function __process_data_generic($key, $data){
		if($data === NULL){ return $this->content[$key]; }
		elseif($data === FALSE){
			$this->content[$key] = NULL;
			unlink($this->content[$key]);
		}else{ $this->content[$key] = $data; }

		return $this;
	}

	// ----------------------------------------------

	function generate_order(){
		return base64_encode(json_encode($this->content));
	}

	function generate_sig(){
		$keydec = base64_decode($this->key);
		$order = $this->generate_order();
		$keyenc = $this->encrypt_3DES($this->order(), $keydec);
		$sig = $this->mac256($order, $keyenc);	//(PHP 5 >= 5.1.2)
		return base64_encode($sig);
	}

	function validate($data, $hash){
		$hash = strtr($hash, '-_', '+/');
		$keydec = base64_decode($this->key);
		$this->content = json_decode(base64_decode($data), true);
		$keyenc = $this->encrypt_3DES($this->content['Ds_Order'], $keydec);
		$sig = $this->mac256($data, $keyenc); // Hacer el HASH con el JSON que hemos recibido
		$sig = base64_encode($sig);
		$this->reset();
		return ($hash == $sig);
	}

} ?>
