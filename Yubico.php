<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Yubico extends CI_Model {

	private $clid = 0;
	private $key = "MY YUBICO KEY";
	var $_url_list;
	var $_url_index;
	var $_https = FALSE;
	var $_lastquery;
	var $_httpsverify = TRUE;
	var $_url;
	var $_response;
	

	function parsePasswordOTP($str, $delim = '[:]'){
		if (!preg_match("/^((.*)" . $delim . ")?" . "(([cbdefghijklnrtuv]{0,16})" ."([cbdefghijklnrtuv]{32}))$/i", $str, $matches)) {
			/* Dvorak? */
			if (!preg_match("/^((.*)" . $delim . ")?" ."(([jxe\.uidchtnbpygk]{0,16})" ."([jxe\.uidchtnbpygk]{32}))$/i", $str, $matches)) {
				return FALSE;
			} else {
				$ret['otp'] = strtr($matches[3], "jxe.uidchtnbpygk", "cbdefghijklnrtuv");
			}
		} else {
			$ret['otp'] = $matches[3];
		}
		$ret['password'] = $matches[2];
		$ret['prefix'] = $matches[4];
		$ret['ciphertext'] = $matches[5];

		return $ret;
	}
	function setURLpart($url)
	{
		$this->_url = $url;
	}

	function getURLpart()
	{
		if ($this->_url) {
			return $this->_url;
		} else {
			return "api.yubico.com/wsapi/verify";
		}
	}

	function getNextURLpart(){
		if ($this->_url_list) $url_list=$this->_url_list;
		else $url_list=array('api.yubico.com/wsapi/2.0/verify',
					 'api2.yubico.com/wsapi/2.0/verify', 
					 'api3.yubico.com/wsapi/2.0/verify', 
					 'api4.yubico.com/wsapi/2.0/verify',
					 'api5.yubico.com/wsapi/2.0/verify');
		
		if ($this->_url_index>=count($url_list)) return false;
		else return $url_list[$this->_url_index++];
	}

	function URLreset()
	{
		$this->_url_index=0;
	}


// 	function verify($token, $use_timestamp=null, $wait_for_all=False,$sl=null, $timeout=null){
// 		/* Construct parameters string */
// 		$ret = $this->parsePasswordOTP($token);
// 		if (!$ret) { return FALSE; } // Could not parse Yubikey OTP
// 		
// 		$params = array(
// 			'id'=>$this->clid, 
// 			'otp'=>$ret['otp'],
// 			'nonce'=>md5(uniqid(rand()))
// 		);
// 		/* Take care of protocol version 2 parameters */
// 		if ($use_timestamp) $params['timestamp'] = 1;
// 		if ($sl) $params['sl'] = $sl;
// 		if ($timeout) $params['timeout'] = $timeout;
// 		ksort($params);
// 		$parameters = '';
// 		foreach($params as $p=>$v){ $parameters .= "&" . $p . "=" . $v; }
// 		$parameters = ltrim($parameters, "&");
// 
// 		/* Generate signature. */
// 		if($this->key <> "") {
// 			$signature = base64_encode(hash_hmac('sha1', $parameters, $this->key, true));
// 			$signature = preg_replace('/\+/', '%2B', $signature);
// 			$parameters .= '&h=' . $signature;
// 		}
// 
// 		/* Generate and prepare request. */
// 		// $this->_lastquery=null;
// 		$this->URLreset();
// 		$mh = curl_multi_init();
// 		$ch = array();
// 		while($URLpart = $this->getNextURLpart()){
// 			/* Support https. */
// 			if ($this->_https) {
// 				$query = "https://";
// 			} else {
// 				$query = "http://";
// 			}
// 			$query .= $URLpart . "?" . $parameters;
// 
// 			if ($this->_lastquery) { $this->_lastquery .= " "; }
// 			$this->_lastquery .= $query;
// 
// 			$handle = curl_init($query);
// 			curl_setopt($handle, CURLOPT_USERAGENT, "Auth_Yubico");
// 			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
// 			if (!$this->_httpsverify) {
// 				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
// 				curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
// 			}
// 			curl_setopt($handle, CURLOPT_FAILONERROR, true);
// 				/* If timeout is set, we better apply it here as well
// 					 in case the validation server fails to follow it. 
// 				*/ 
// 					 if ($timeout) curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
// 					 curl_multi_add_handle($mh, $handle);
// 
// 					 $ch[(int)$handle] = $handle;
// 			 }
// 
// 			 /* Execute and read request. */
// 			 $this->_response=null;
// 			 $replay=FALSE;
// 			 $valid=True;
// 			 do {
// 			 	/* Let curl do its work. */
// 			 	while (($mrc = curl_multi_exec($mh, $active))
// 			 		== CURLM_CALL_MULTI_PERFORM)
// 			 		;
// 
// 			 	while ($info = curl_multi_info_read($mh)) {
// 			 		if ($info['result'] == CURLE_OK) {
// 
// 			 			/* We have a complete response from one server. */
// 
// 			 			$str = curl_multi_getcontent($info['handle']);
// 			 			$cinfo = curl_getinfo ($info['handle']);
// 
// 		if ($wait_for_all) { # Better debug info
// 			$this->_response .= 'URL=' . $cinfo['url'] ."\n"
// 			. $str . "\n";
// 		}
// 
// 		if (preg_match("/status=([a-zA-Z0-9_]+)/", $str, $out)) {
// 			$status = $out[1];
// 
// 			/* 
// 			 * There are 3 cases.
// 			 *
// 			 * 1. OTP or Nonce values doesn't match - ignore
// 			 * response.
// 			 *
// 			 * 2. We have a HMAC key.	If signature is invalid -
// 			 * ignore response.	Return if status=OK or
// 			 * status=REPLAYED_OTP.
// 			 *
// 			 * 3. Return if status=OK or status=REPLAYED_OTP.
// 			 */
// 			if (!preg_match("/otp=".$params['otp']."/", $str) ||
// 				!preg_match("/nonce=".$params['nonce']."/", $str)) {
// 				/* Case 1. Ignore response. */
// 		} 
// 		elseif ($this->key <> "") {
// 			/* Case 2. Verify signature first */
// 			$rows = explode("\r\n", trim($str));
// 			$response=array();
// 			while (list($key, $val) = each($rows)) {
// 				/* = is also used in BASE64 encoding so we only replace the first = by # which is not used in BASE64 */
// 				$val = preg_replace('/=/', '#', $val, 1);
// 				$row = explode("#", $val);
// 				$response[$row[0]] = $row[1];
// 			}
// 
// 			$parameters=array('nonce','otp', 'sessioncounter', 'sessionuse', 'sl', 'status', 't', 'timeout', 'timestamp');
// 			sort($parameters);
// 			$check=Null;
// 			foreach ($parameters as $param) {
// 				if (array_key_exists($param, $response)) {
// 					if ($check) $check = $check . '&';
// 					$check = $check . $param . '=' . $response[$param];
// 				}
// 			}
// 
// 			$checksignature =
// 			base64_encode(hash_hmac('sha1', utf8_encode($check),
// 				$this->key, true));
// 
// 			if($response['h'] == $checksignature){
// 				if ($status == False) {
// 					if (!$wait_for_all) { $this->_response = $str; }
// 					$replay=True;
// 				} 
// 				if ($status == true) {
// 					if (!$wait_for_all) { $this->_response = $str; }
// 					$valid=True;
// 				}
// 			}
// 		} else {
// 			/* Case 3. We check the status directly */
// 			if ($status == false) {
// 				if (!$wait_for_all) { $this->_response = $str; }
// 				$replay=True;
// 			} 
// 			if ($status == True) {
// 				if (!$wait_for_all) { $this->_response = $str; }
// 				$valid=True;
// 			}
// 		}
// 	}
// 	if (!$wait_for_all && ($valid || $replay)) 
// 	{
// 		/* We have status=OK or status=REPLAYED_OTP, return. */
// 		foreach ($ch as $h) {
// 			curl_multi_remove_handle($mh, $h);
// 			curl_close($h);
// 		}
// 		curl_multi_close($mh);
// 		if ($replay) return false; //('REPLAYED_OTP');
// 		if ($valid) return true;
// 
// 		return $status;
// 	}
// 
// 	curl_multi_remove_handle($mh, $info['handle']);
// 	curl_close($info['handle']);
// 	unset ($ch[(int)$info['handle']]);
// }
// 	curl_multi_select($mh);
// }
// 
// //var_dump($active);
// 
// } while ($active);
// 
// 		/* Typically this is only reached for wait_for_all=true or
// 		 * when the timeout is reached and there is no
// 		 * OK/REPLAYED_REQUEST answer (think firewall).
// 		 */
// 
// 		foreach ($ch as $h) {
// 			curl_multi_remove_handle ($mh, $h);
// 			curl_close ($h);
// 		}
// 		curl_multi_close ($mh);
// 		
// 		if ($replay) return FALSE; //REPLAYED_OTP
// 		if ($valid) return TRUE;
// 		//var_dump($replay);
// 		//var_dump($valid);
//			 echo"Prueba ";
// 		return FALSE;	// NO_VALID_ANSWER
// 	}

	function valid_token($token){
		if($this->verify($token)){ return substr($token, 0, 12); }
		return FALSE;
	}

	function verify($token, $use_timestamp=null, $wait_for_all=False,$sl=null, $timeout=null){
		/* Construct parameters string */
		$ret = $this->parsePasswordOTP($token);
		if (!$ret) { return -1; } // ('Could not parse Yubikey OTP');

		$params = array(
			'id'=>$this->clid, 
			'otp'=>$ret['otp'],
			'nonce'=>md5(uniqid(rand()))
		);
		/* Take care of protocol version 2 parameters */
		if ($use_timestamp){ $params['timestamp'] = 1; }
		if ($sl){ $params['sl'] = $sl; }
		if ($timeout){ $params['timeout'] = $timeout; }
		ksort($params);
		$parameters = '';
		foreach($params as $p=>$v){ $parameters .= "&" . $p . "=" . $v; }
		$parameters = ltrim($parameters, "&");
		
		/* Generate signature. */
		if($this->key <> "") {
			$signature = base64_encode(hash_hmac('sha1', $parameters,
				$this->key, true));
			$signature = preg_replace('/\+/', '%2B', $signature);
			$parameters .= '&h=' . $signature;
		}

		/* Generate and prepare request. */
		$this->_lastquery=null;
		$this->URLreset();
		$mh = curl_multi_init();
		$ch = array();
		while($URLpart=$this->getNextURLpart()){
			/* Support https. */
			if ($this->_https){ $query = "https://"; }
			else { $query = "http://"; }
			$query .= $URLpart . "?" . $parameters;

			if ($this->_lastquery) { $this->_lastquery .= " "; }
			$this->_lastquery .= $query;

			$handle = curl_init($query);
			curl_setopt($handle, CURLOPT_USERAGENT, "PEAR Auth_Yubico");
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			if (!$this->_httpsverify) {
				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
			}
			curl_setopt($handle, CURLOPT_FAILONERROR, true);
			/* If timeout is set, we better apply it here as well
			 in case the validation server fails to follow it. 
			*/ 
			 if ($timeout) curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
			 curl_multi_add_handle($mh, $handle);
			 
			 $ch[(int)$handle] = $handle;
		}

		/* Execute and read request. */
		$this->_response=null;
		$replay=False;
		$valid=true;
		do {
			/* Let curl do its work. */
			while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);

			while ($info = curl_multi_info_read($mh)) {
				if ($info['result'] == CURLE_OK) {
		
					/* We have a complete response from one server. */
		
					$str = curl_multi_getcontent($info['handle']);
					$cinfo = curl_getinfo ($info['handle']);
					
					if ($wait_for_all) { # Better debug info
						$this->_response .= 'URL=' . $cinfo['url'] ."\n" . $str . "\n";
					}
		
					if (preg_match("/status=([a-zA-Z0-9_]+)/", $str, $out)) {
						$status = $out[1];
		
						/* 
						 * There are 3 cases.
						 *
						 * 1. OTP or Nonce values doesn't match - ignore
						 * response.
						 *
						 * 2. We have a HMAC key.	If signature is invalid -
						 * ignore response.	Return if status=OK or
						 * status=REPLAYED_OTP.
						 *
						 * 3. Return if status=OK or status=REPLAYED_OTP.
						 */
						if (!preg_match("/otp=".$params['otp']."/", $str) ||
							!preg_match("/nonce=".$params['nonce']."/", $str)) {
							/* Case 1. Ignore response. */
						} elseif ($this->key <> "") {
							/* Case 2. Verify signature first */
							$rows = explode("\r\n", trim($str));
							$response=array();
							while (list($key, $val) = each($rows)) {
								/* = is also used in BASE64 encoding so we only replace the first = by # which is not used in BASE64 */
								$val = preg_replace('/=/', '#', $val, 1);
								$row = explode("#", $val);
								$response[$row[0]] = $row[1];
							}
							
							$parameters=array('nonce','otp', 'sessioncounter', 'sessionuse', 'sl', 'status', 't', 'timeout', 'timestamp');
							sort($parameters);
							$check=Null;
							foreach ($parameters as $param) {
								if (array_key_exists($param, $response)) {
									if ($check){ $check = $check . '&'; }
									$check = $check . $param . '=' . $response[$param];
								}
							}
		
							$checksignature = base64_encode(hash_hmac('sha1', utf8_encode($check), $this->key, true));
		
							if($response['h'] == $checksignature) {
								if ($status == 'REPLAYED_OTP') {
									if (!$wait_for_all) { $this->_response = $str; }
									$replay=True;
								} 
								if ($status == 'OK') {
									if (!$wait_for_all) { $this->_response = $str; }
									$valid=True;
								}
							}
						} else {
							/* Case 3. We check the status directly */
							if ($status == 'REPLAYED_OTP') {
								if (!$wait_for_all) { $this->_response = $str; }
								$replay=True;
							} 
							if ($status == 'OK') {
								if (!$wait_for_all) { $this->_response = $str; }
								$valid=True;
							}
						}
					} // <- if
					if (!$wait_for_all && ($valid || $replay)){
						/* We have status=OK or status=REPLAYED_OTP, return. */
						foreach ($ch as $h) {
							curl_multi_remove_handle($mh, $h);
							curl_close($h);
						}
						curl_multi_close($mh);
						if ($replay){ return -2; } // PEAR::raiseError('REPLAYED_OTP');
						

						if ($valid){ return TRUE; }
						return -3; // PEAR::raiseError($status);
					}
					
					curl_multi_remove_handle($mh, $info['handle']);
					curl_close($info['handle']);
					unset ($ch[(int)$info['handle']]);
				}
				curl_multi_select($mh);
			}
		} while ($active);

		/* Typically this is only reached for wait_for_all=true or
		 * when the timeout is reached and there is no
		 * OK/REPLAYED_REQUEST answer (think firewall).
		 */

		foreach ($ch as $h) {
			curl_multi_remove_handle ($mh, $h);
			curl_close ($h);
		}
		curl_multi_close ($mh);
		
		if ($replay) return -4; // PEAR::raiseError('REPLAYED_OTP');


		if ($valid) return 4;
		return -5; // PEAR::raiseError('NO_VALID_ANSWER');
	}


} ?>