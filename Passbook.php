<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Passbook extends CI_Model {

	private $_passbook = array();
	private $_content = array();
	private $_card_type = NULL;

	function initialize(){
		$this->_passbook = array('formatVersion' => 1);
		return $this;
	}

	function done(){
		$this->_passbook[$this->_card_type] = $this->_content;
		return $this->_passbook;
	}

	function passTypeIdentifier($s){
		if(substr($s, 0, 5) != "pass."){ $s = "pass.{$s}"; }
		$this->_passbook['passTypeIdentifier'] = $s;
		return $this;
	}

	function serialNumber($data){
		$this->_passbook['serialNumber'] = $data;
		return $this;
	}

	function teamIdentifier($data){
		$this->_passbook['teamIdentifier'] = $data;
		return $this;
	}

	function webServiceURL($data){
		$this->_passbook['webServiceURL'] = $data;
		return $this;
	}

	function authenticationToken($data){
		$this->_passbook['authenticationToken'] = $data;
		return $this;
	}

	function organizationName($data){
		$this->_passbook['organizationName'] = $data;
		return $this;
	}

	function description($data){
		$this->_passbook['description'] = $data;
		return $this;
	}

	function labelColor($red, $green = NULL, $blue = NULL){
		$this->_passbook['labelColor'] = $this->_parseColor($red, $green, $blue);
		return $this;
	}

	function foregroundColor($red, $green = NULL, $blue = NULL){
		$this->_passbook['foregroundColor'] = $this->_parseColor($red, $green, $blue);
		return $this;
	}

	function backgroundColor($red, $green = NULL, $blue = NULL){
		$this->_passbook['backgroundColor'] = $this->_parseColor($red, $green, $blue);
		return $this;
	}

	function cardType($type){
		// "boardingPass"
		$this->_card_type = $type;
		return $this;
	}

	function addHeaderField($key, $label, $value, $changeMessage = NULL, $align = NULL){
		$this->_content['headerFields'] = $this->_parseContent($key, $label, $value, $changeMessage, $align);
		return $this;
	}

	function addPrimaryField($key, $label, $value, $changeMessage = NULL, $align = NULL){
		$this->_content['primaryFields'] = $this->_parseContent($key, $label, $value, $changeMessage, $align);
		return $this;
	}

	function addSecondaryField($key, $label, $value, $changeMessage = NULL, $align = NULL){
		$this->_content['secondaryFields'] = $this->_parseContent($key, $label, $value, $changeMessage, $align);
		return $this;
	}

	function addAuxiliaryField($key, $label, $value, $changeMessage = NULL, $align = NULL){
		$this->_content['auxiliaryFields'] = $this->_parseContent($key, $label, $value, $changeMessage, $align);
		return $this;
	}

	function addBackField($key, $label, $value, $changeMessage = NULL, $align = NULL){
		$this->_content['backFields'] = $this->_parseContent($key, $label, $value, $changeMessage, $align);
		return $this;
	}

	function addLocation($lat, $lon, $text = NULL){
		$l = array(
			'latitude' => $lat,
			'longitude' => $lon
		);
		if(!empty($text)){ $l['relevantText'] = $text; }

		$this->_passbook['locations'][] = $l;
		return $this;
	}

	function transitType($type){
		// PKTransitTypeAir, PKTransitTypeTrain
		$this->_content['transitType'] = $type;
		return $this;
	}

	function relevantDate($date){
		if(!is_numeric($date)){ $date = strtotime($date); } // Si no es Timestamp, convertir
		$this->_passbook['relevantDate'] = date("c", $date); // DATE_ATOM
		return $this;
	}

	function addBarcode($m, $f, $txt = NULL, $mE = NULL){
		// PKBarcodeFormatPDF417
		// PKBarcodeFormatQR
		// PKBarcodeFormatAztec
		// PKBarcodeFormatCode128
		// PKBarcodeFormatNone
		// iso-8859-1, utf-8
		$t = array(
			'message' => $m,
			'format' => $f,
			'messageEncoding' => (empty($mE) ? "utf-8" : $mE),
			'altText' => (empty($txt) ? "CODE" : $txt)
		);

		$this->_passbook['barcode'] = $t;
		return $this;
	}

	function _parseColor($r, $g = NULL, $b = NULL){
		if(substr($r, 0, 1) == "#" and (strlen($r) == (1+6) or strlen($r) == (1+3)) ){
			$c = substr($r, 1);
			if(strlen($c) == 3){ $c = "{$c[0]}{$c[0]}{$c[1]}{$c[1]}{$c[2]}{$c[2]}"; }
			$r = hexdec(substr($c, 0, 2));
			$g = hexdec(substr($c, 2, 2));
			$b = hexdec(substr($c, 4, 2));
		}

		if($r > 255){ $r = 255; }
		if($g > 255){ $g = 255; }
		if($b > 255){ $b = 255; }

		return "rgb({$r}, {$g}, {$b})";
	}

	function _parseContent($k, $l, $v, $alg = NULL $cM = NULL){
		$t = array(
			'key' => $k,
			'label' => $l,
			'value' => $v,
		);

		// PKTextAlignmentLeft
		// PKTextAlignmentCenter
		if(!empty($cM)){ $t['changeMessage'] = $cM; }
		if(!empty($cM)){ $t['textAlignment'] = "PKTextAlignmentRight"; }
		return $t;
	}
	

}