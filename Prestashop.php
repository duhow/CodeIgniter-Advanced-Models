<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Prestashop extends CI_Model {

	private $db;
	private $lang = 'en';
	private $products_locked = array();
	private $cookie_key = "";

	function __construct(){
		parent::__construct();
		$this->db = $this->load->database('prestashop',TRUE);
	}

	// --------------------------------------------
	//   Products
	// --------------------------------------------

	function products($active = TRUE, $shop = 1){
		$lang = $this->get_language_code($this->language());

		$this->db->select(array('product.id_product','product.id_category_default','product.reference','product.price','product_lang.name'));
		$this->db->from('product');
		$this->db->join('product_lang', 'product_lang.id_product = product.id_product');
		if($active){$this->db->where('active', TRUE);}
		// $this->db->where('available_for_order', true);
		$this->db->where('product.id_shop_default', $shop);
		$this->db->where('id_shop', $shop);
		$this->db->where('id_lang', $lang);

		$query = $this->db->get();
		if($query->num_rows()>0){
			foreach($query->result_array() as $p){
				if(in_array($p['id_product'], $this->products_locked)){ continue; }
				$products[$p['id_product']] = $p;

			}
			return $products;
		}else{
			return FALSE;
		}
		// category_group
	}

	function product($id, $language = NULL, $shop = 1){
		if(in_array($id, $this->products_locked)){ return false; }
		if($language !== NULL){ $lang = $language; }
		else{ $lang = $this->get_language_code($this->language()); }

		$select = [
			'product.id_product',
			'product.id_category_default',
			'product.reference',
			'product.price',
			'product_lang.name',
		];
		$this->db->select($select)->from('product')->join('product_lang', 'product_lang.id_product = product.id_product');
		$this->db
			->where('product.id_product', $id)
			->where('product.id_shop_default', $shop)
			->where('id_shop', $shop)
			->where('id_lang', $lang);
		// $this->db->where('available_for_order', true);

		$query = $this->db->get();
		if($query->num_rows() == 1){
			$product = $query->row_array();
			$product['price'] = round($product['price'], 2);
			$product['image'] = $this->product_image($product['id_product']);

			return $product;
		}else{
			return false;
		}
	}

	function get_product_by_reference($reference){
		$query = $this->db
				->where('reference', $reference)
				->get('product');

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{ return array(); }
	}

	function product_price($pid, $clid = NULL, $search = NULL){
		// PENDIENTE DE HACER
		// DAVID - JUAN
		$normal = $this->db
			->select('price')
			->where('id_product', $pid)
			->where('id_shop', 1)
		->get('product');
		if($normal->num_rows() == 1){ $normal = round($normal->row()->price, 2); }

		// Sacar diferente precio en función de un producto para un cliente, TABLA -> specific_price
		// Esto es para ver: si un cliente es de un país (Estados Unidos), el precio del curso será el que haya en la tabla (+25%)
		if($clid === NULL && $search === NULL){
			if($normal){ return $normal; }
			return NULL;
		}elseif(is_numeric($clid)){
			$client = $this->customer($clid);
			if(empty($client)){ return FALSE; }

			$addresses = $this->customer_addresses($clid);
			if(empty($addresses)){ return FALSE; }

			$addr = $this->customer_address($addresses[0]);

			$query = $this->db
				->where('id_product', $pid)
				->group_start()
					->where('id_country', $addr['id_country'])
					->or_where('id_customer', $clid)
				->group_end()
				->get('specific_price');

			if($query->num_rows() > 0){
				// De forma correcta, habría que ver si hay un reduce / discount o no.
				if($query->num_rows() == 1){
					if($addr['id_country'] == 21){  // Es de Estados Unidos -> +25%
						return $query->row()->price + ($query->row()->price * 0.25);
					}else{
						return $query->row()->price;
					}
				 }
			}else{
				return $normal;
			}
		}elseif(!empty($search) && is_array($search)){
		}
	}

	function product_price_conditions($prod, $data){
		$product = $this->product($prod);
		$precioFinal = $product['price'];

		// En funcion de DATA:
		//	-> Si es mail (filter_var), cojer datos de cliente y filtrar segun su pais o id_customer
		if(filter_var($data, FILTER_VALIDATE_EMAIL) !== FALSE){
			$id_customer = $this->customer($data, TRUE);

			$query = $this->db
						->where('id_customer', $id_customer)
					->get('address');

			if($query->num_rows() == 1){
				$data = $query->row()->id_country;

			}else{
				return $precioFinal;
			}
		}

		$country_id = $this->parse_country($data);
		if($country_id !== FALSE){
			$query = $this->db
						->where('id_country', $country_id)
						->where('id_product', $prod)
					->get('specific_price');

			if($query->num_rows() == 1){
				$precioFinal = $query->row()->price;
			}
		}

		return $precioFinal;
	}

	function parse_country($text){
		// Buscar Estados Unidos y devolver su ID 21 (?)
		$query = $this->db
				->select('id_country')
				->where('name', $text)
				->or_where('id_country', $text)
			->get('country_lang');

		if($query->num_rows() > 0){
			return $query->row()->id_country;
		}else{ return false; }
	}

	function product_attribute($prod, $default = FALSE){
		// ps_product_attribute_combination
		if($default === TRUE){ $this->db->where('default_on', TRUE); }
		$query = $this->db
			->where('id_product', $prod)
		->get('product_attribute');
		if($query->num_rows() > 0){ return $query->result_array(); }
		return array();
	}

	function product_image($pid, $all = false){
		$this->db
			->where('id_product', $pid)
			->order_by('position', 'ASC');

		// Si todos es false, por defecto se muestra la que es de portada (cover)
		if(!$all){
		$this->db
			->select('id_image')
			->where('cover', TRUE)
			->limit(1);
		}

		$query = $this->db->get('image');

		if($query->num_rows() == 1){ return $query->row()->id_image; }
		elseif($query->num_rows()>0){ return $query->result_array(); }
		else{ return false; }
	}

	function simple_product_features($id, $new = FALSE){
		$tmp_feat = $this->features();
		$tmp_feat_val = $this->product_features($id, array_keys($tmp_feat));

		if($tmp_feat == false or $tmp_feat_val == false){return false;}

		foreach($tmp_feat as $feat){
			if($new){
				@$features[$feat['name']] = $tmp_feat[$feat['id_feature']][$tmp_feat_val[$feat['id_feature']]];
			}else{
				@$features[$feat['id_feature']]['name'] = $feat['name'];
				@$features[$feat['id_feature']]['value'] = $tmp_feat[$feat['id_feature']][$tmp_feat_val[$feat['id_feature']]];
			}

			// Hidden error in case that there is no feature assigned.
		}
		return $features;
	}

	function product_features($id, $features){
		$this->db->where('id_product', $id);
		$this->db->where_in('id_feature', $features);

		$query = $this->db->get('feature_product');
		if($query->num_rows()>0){
			foreach($query->result_array() as $feat){ $features[$feat['id_feature']] = $feat['id_feature_value']; }
			return $features;
		}else{
			return false;
		}
	}

	function features(){
		$lang = $this->get_language_code($this->language());

		$this->db->select(array('feature.id_feature', 'feature.position', 'feature_lang.name'));
		$this->db->from('feature');
		$this->db->join('feature_lang', 'feature_lang.id_feature = feature.id_feature');
		$this->db->where('id_lang', $lang);
		// $this->db->order_by('position', 'asc');

		$query = $this->db->get();
		if($query->num_rows()>0){
			foreach($query->result_array() as $feat){ $features[$feat['id_feature']] = $feat; }

			$this->db->select(array('feature_value.id_feature', 'feature_value.id_feature_value', 'feature_value_lang.value'));
			$this->db->from('feature_value');
			$this->db->join('feature_value_lang', 'feature_value_lang.id_feature_value = feature_value.id_feature_value');
			$this->db->where('id_lang', $lang);
			$this->db->where_in('id_feature', array_keys($features));

			$query = $this->db->get();
			if($query->num_rows()>0){
				foreach($query->result_array() as $feat){ $features[$feat['id_feature']][$feat['id_feature_value']] = $feat['value']; }
				return $features;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	function categories($active = true, $shop = 1){
		$lang = $this->get_language_code($this->language());

		$this->db->select(array('category.*', 'category_lang.name', 'category_lang.description'));
		$this->db->from('category');
		$this->db->join('category_lang', 'category_lang.id_category = category.id_category');
		if($active){$this->db->where('active', true);}
		$this->db->where('id_shop_default', $shop);
		$this->db->where('id_lang', $lang);
		$query = $this->db->get();
		if($query->num_rows()>0){return $query->result_array();
		}else{return false;}
	}

	// --------------------------------------------
	//   Order
	// --------------------------------------------

	function order($order, $full = FALSE){
		$select = [
			'id_order AS id',
			'reference',
			'id_customer AS cid',
			'id_lang AS lang',
			'id_address_delivery AS addrid',
			'current_state AS status',
			'total_discounts_tax_incl AS discount',
			'total_products AS total',
			'total_paid_real',
			'date_add AS date',
			'date_upd AS date_last',
			'valid',
		];

		if($full){ $select = '*'; }
		$this->db
			->select($select)
			->where('reference', $order)
			->or_where('id_order', $order);
		$query = $this->db->get('orders');
		if($query->num_rows() == 1){
			$order = $query->row_array();

			if(isset($order['id_order'])){ $id = $order['id_order']; }
			else{ $id = $order['id']; }

			$order['details'] = $this->order_details($id);
			$order['payments'] = $this->order_payments($id);
			return $order;
		}
	}

	function get_customer_by_order($order){
		$query = $this->db
			->select('id_customer')
			->where('reference', $order)
			->or_where('id_order', $order)
		->get('orders');

		return ($query->num_rows() == 1 ? $query->row()->id_customer : NULL);
	}

	function order_reference_generate($len = 9){
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

		do{
			$ref = '';
			for ($i = 0; $i < $len; $i++) { $ref .= $chars[mt_rand(0, strlen($chars) - 1)]; }
		}while($this->order_exists($ref));

		return $ref;
	}

	function order_create($clid, $aid = NULL, $ref = NULL, $extra = NULL){

		if(empty($ref)){ $ref = $this->order_reference_generate(); }

		$customer = $this->customer($clid);
		if($customer === FALSE){ return false; }

		if(empty($aid)){
			$aid = $this->customer_addresses($clid);

			// Cogemos la primera
			$aid = $aid[0]['id_address'];
		}

		$data = array(
				'reference' => $ref,
				'id_shop_group' => 1,
				'id_shop' => 1,
				'id_carrier' => 0,
				'id_lang' => $this->get_language_code(),
				'id_customer' => $clid,
				'id_currency' => 1,
				'id_address_delivery' => $aid,
				'id_address_invoice' => $aid,
				'current_state' => 10, // En espera de pago por banco
				'secure_key' => md5($ref .date("ymdHis") .mt_rand(100000,999999)),
				'payment' => "Transferencia Bancaria", // Bankwire
				'conversion_rate' => 1,
				'module' => "bankwire", // Bankwire Default
				'recyclable' => 0,
				'gift' => 0,
				'gift_message' => "",
				'mobile_theme' => 0,
				'shipping_number' => 0,
				'total_discounts' => 0,
				'total_discounts_tax_incl' => 0,
				'total_discounts_tax_excl' => 0,
				'total_paid' => 0,
				'total_paid_tax_incl' => 0,
				'total_paid_tax_excl' => 0,
				'total_paid_real' => 0,
				'total_products' => 0,
				'total_products_wt' => 0,
				'total_shipping' => 0,
				'total_shipping_tax_incl' => 0,
				'total_shipping_tax_excl' => 0,
				'carrier_tax_rate' => 0,
				'total_wrapping' => 0,
				'total_wrapping_tax_incl' => 0,
				'total_wrapping_tax_excl' => 0,
				// 'round_mode' => 0,
				// 'round_type' => 0,
				'invoice_number' => 0,
				'delivery_number' => 0,
				'invoice_date' => date("Y-m-d H:i:s"),
				'delivery_date' => "0000-00-00 00:00:00",
				'valid' => 0,
				'date_add' => date("Y-m-d H:i:s"),
				'date_upd' => date("Y-m-d H:i:s")
			);

		// Set raw data
		if(!empty($extra)){ foreach($extra as $k => $v){ $data[$k] = $v; } }

		$this->db->insert('orders', $data);
		return $this->db->insert_id();
	}

	function order_product_add($order, $product, $manual_price = NULL, $amount = 1, $extra = NULL){
		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$product = $this->product($product);
		if(empty($product)){ return false; } // No existe producto!

		if(empty($manual_price)){ $manual_price = $product['price']; }

		$data = [
			'id_order' => $order,
			'id_order_invoice' => 0,
			'id_warehouse' => 0,
			'id_shop' => 1,
			'product_id' => $product['id_product'],
			'product_attribute_id' => 0, // WARNING, May change!
			'product_name' => $product['name'],
			'product_quantity' => $amount,
			'product_quantity_in_stock' => $amount,
			'product_quantity_refunded' => 0,
			'product_quantity_return' => 0,
			'product_quantity_reinjected' => 0,
			'product_price' => $manual_price, // CHANGED
			'reduction_percent' => 0,
			'reduction_amount' => 0,
			'reduction_amount_tax_incl' => 0,
			'reduction_amount_tax_excl' => 0,
			'group_reduction' => 0,
			'product_quantity_discount' => 0,
			'product_ean13' => '',
			'product_upc' => '',
			'product_reference' => $product['reference'],
			'product_supplier_reference' => '',
			'product_weight' => 0,
			'tax_computation_method' => 0,
			'tax_name' => '',
			'tax_rate' => 0,
			'ecotax' => 0,
			'ecotax_tax_rate' => 0,
			'discount_quantity_applied' => 0,
			'download_hash' => '',
			'download_nb' => '',
			'download_deadline' => '',
			'total_price_tax_incl' => $manual_price * $amount,
			'total_price_tax_excl' => $manual_price * $amount,
			'unit_price_tax_incl' => $manual_price,
			'unit_price_tax_excl' => $manual_price,
			'total_shipping_price_tax_incl' => 0,
			'total_shipping_price_tax_excl' => 0,
			'purchase_supplier_price' => 0,
			'original_product_price' => $product['price'], // Original = APLICADO POR CART RULE.
		];

		if(!empty($extra)){
			foreach($extra as $k => $v){ $data[$k] = $v; }
		}

		$this->db->insert('order_detail',$data);
		$detailid = $this->db->insert_id();

		// Update Order Total Price
		$this->order_update_total_price($order);

		return $detailid;
	}

	function order_update_total_price($order){
		// Select order_detail
		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$details = $this->order_details($order, TRUE);
		$order_data = $this->order($order, TRUE);

		// order data tiene que tener total_discounts_tax_incl y demás.

		if(!empty($details)){
			$total = 0;
			$discount = 0;
			foreach($details as $p){
				// Total Price ya incluye la suma de todos, así que no problem.
				$total += $p['total_price_tax_incl']; // * $p['amount'];
				$discount += $p['reduction_amount_tax_incl'];
			}

			$data = [
				'total_products' => $total,
				'total_products_wt' => $total,
				'total_paid' => ($total - $discount - $order_data['total_discounts_tax_incl']),
				'total_paid_tax_excl' => ($total - $discount - $order_data['total_discounts_tax_incl']),
				'total_paid_tax_incl' => ($total - $discount - $order_data['total_discounts_tax_incl']),
			];

			$this->db->where('id_order', $order);
			return $this->db->update('orders', $data);
		}
	}

	function get_order_id($ref){
		$this->db->select('id_order')->where('reference', $ref);
		$query = $this->db->get('orders');

		if($query->num_rows() == 1){ return $query->row()->id_order; }
		else{ return false; }
	}

	function get_order_ref($id){
		$this->db->select('reference')->where('id_order', $id);
		$query = $this->db->get('orders');

		if($query->num_rows() == 1){ return $query->row()->reference; }
		else{ return false; }
	}

	function order_exists($order, $return = false){
		// secure_key = md5(uniqid(rand(), true))

		$this->db->where('id_order', (int)$order);
		$this->db->or_where('reference', $order);

		$query = $this->db->get('orders');
		if ($query->num_rows() == 1){
			if($return){ return $query->row()->reference; }
			else{ return true; }
		}
		return false;
	}

	function order_details($orderid, $full = FALSE){
		$select = [
			'id_order_detail', // AS id
			'id_order_invoice', // AS invoiceid
			'product_id',
			'product_name',
			'product_reference',
			'product_quantity',
			'product_price',
			'reduction_percent',
			'reduction_amount',
			'unit_price_tax_incl',
			'original_product_price',
		];

		if($full){ $select = "*"; }

		$this->db->select($select)->where('id_order', $orderid);
		$query = $this->db->get('order_detail');
		if($query->num_rows()>0){
			foreach($query->result_array() as $d){
				$final[$d['id_order_detail']] = $d;
				$final[$d['id_order_detail']]['image'] = $this->product_image($d['product_id']);
				// $final[$d['id_order_detail']]['product_price'] = $this->product_price($) // TODO
			}
			return $final;
		}
		return false;
	}

	function order_change_state($ref, $state){
		if(!is_numeric($ref)){ $ref = $this->get_order_id($ref); }
		$this->db->where('id_order_state', $state);
		$query = $this->db->get('order_state');
		if($query->num_rows() == 1){ $state_info = $query->row_array(); }
		else{ return false; }
		$this->db->set('current_state', $state);
		$this->db->set('valid', (bool)$state_info['logable']);
		$this->db->where('id_order', $ref)->update('orders');
		$data = [
			'id_employee' => 0,
			'id_order' => $ref,
			'id_order_state' => $state,
			'date_add' => date("Y-m-d H:i:s"),
		];
		$this->db->insert('order_history', $data);


		return true;
	}

	function order_payment_exists($payment, $ref = NULL){
		$this->db->where('transaction_id', $payment);
		if(!empty($ref)){ $this->db->where('order_reference', $ref); }
		$query = $this->db->get('order_payment');

		return ($query->num_rows()>0);
	}

	function order_payment_add($ref, $payment_ref = NULL, $method, $amount){
		if(is_numeric($ref)){ $ref = $this->get_order_ref($ref); }
		$amount = round($amount, 2);
		if(trim(strtolower($method)) == "paypal"){ $method = " PayPal  "; }
		$payment = [
			'order_reference' => $ref,
			'id_currency' => '1', // EUR
			'amount' => $amount,
			'payment_method' => $method,
			'conversion_rate' => 1,
			'transaction_id' => $payment_ref,
			'date_add' => date("Y-m-d H:i:s"),
		];

		if($this->db->insert('order_payment', $payment)){
			$pid = $this->db->insert_id();
			$ref_id = $this->get_order_id($ref);
			$invoice = $this->order_invoice_id($ref_id);
			if($invoice !== FALSE){
				$data = [
					'id_order_invoice' => $invoice,
					'id_order_payment' => $pid,
					'id_order' => $ref_id,
				];
				$this->db->insert('order_invoice_payment', $data);
			}
			$this->db->set('total_paid_real', 'total_paid_real + ' .$amount, FALSE)->where('reference', $ref)->update('orders');
			if($this->order_waiting_payment($ref)){ $this->order_change_state($this->get_order_id($ref), 14); } // Si estaba esperando el pago, cambiar a Pago Fraccionado (14)
		}else{ return false; }
	}

	function order_payments($ref){
		if(is_numeric($ref)){ $ref = $this->get_order_ref($ref); }
		$this->db->where('order_reference', $ref);
		$query = $this->db->get('order_payment');

		if($query->num_rows()>0){
			foreach($query->result_array() as $o){ $final[$o['id_order_payment']] = $o; }
			return $final;
		}

			// order_payment
			// order_invoice_payment

			// order_payment está todo, pero no todos los pagos tienen ref,
			// asi que primero se mira los invoices y de ahí se saca todo.
	}

	function order_payment_last($ref, $retall = FALSE){
		if(is_numeric($ref)){ $ref = $this->get_order_ref($ref); }
		$query = $this->db
			->where('order_reference', $ref)
			->order_by('date_add', 'DESC')
			->limit(1)
		->get('order_payment');

		if($query->num_rows() == 1){
			if($retall){ return $query->row(); }
			return $query->row()->date_add; // Ya viene formateada en Y-m-d H:i:s
		}

		return FALSE;
	}

	function get_reference_by_date($date, $orders){
		foreach($orders as $order){
			if($order['total_paid_real'] + $order['total_discounts'] < $order['total_products']){
				$res = $this->db
						->where('order_reference', $order['reference'])
						->order_by('id_order_payment', 'DESC')
						->limit(1)
					->get('order_payment');

				$date_add = array_column($res->result_array(), 'date_add');

				if(!empty($date_add)){
					if($date_add[0] < $date){
							$final[] = array_column($res->result_array(), 'order_reference');
					}
				}
			}
		}
		return $final;
	}

	function get_customer_by_date($date, $orders){
		foreach($orders as $order){
		  if($order['total_paid_real'] + $order['total_discounts'] < $order['total_products']){
		    $res = $this->db
		        ->where('order_reference', $order['reference'])
		        ->order_by('id_order_payment', 'DESC')
		        ->limit(1)
		      ->get('order_payment');

		    $date_add = array_column($res->result_array(), 'date_add');

		    if(!empty($date_add)){
		      if($date_add[0] < $date){
		          $final[] = $order['id_customer'];
		      }
		    }
		  }
		}
		return $final;
	}

	function order_discount($order, $amount){
		// OJO! Que el descuento se hace POR PRODUCTO y luego se pone al TOTAL

		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$order = $this->order($order);

		// Si es porcentaje...
		if(!is_numeric($amount) && substr($amount, -1) == "%"){
			// Calcular el % del total.
			$amount = floatval(substr($amount, 0, -1));
			$amount = (($order['total'] * $amount) / 100);
		}

		if($amount > $order['total']){ return FALSE; } // El descuento es superior al importe del pedido, nos sale a devolver! FUERA!!!!

		$data = [
			'total_discounts' => $amount,
			'total_discounts_tax_excl' => $amount,
			'total_discounts_tax_incl' => $amount,
			'total_paid' => $order['total'] - $amount,
			'total_paid_tax_excl' => $order['total'] - $amount,
			'total_paid_tax_incl' => $order['total'] - $amount
		];

		// Y si hacemos un UPDATE ORDER TOTAL PRICE?
		// OJITO CON ESTO, QUE HAY DESCUENTO EN 3 SITIOS!!!!

		$this->db->where('id_order', $order['id']);
		$this->db->update('orders', $data);
	}

	function add_cart_rule(){
		//Añadimos una cart_rule
		$data = array(
			'id_customer' => 0,
			'date_from' => date("Y-m-d H:i:s"),
			'date_to' => date("Y-m-d H:i:s", strtotime("+1 month")),
			'description' =>'',
			'quantity' => 1,
			'quantity_per_user' => 1,
			'priority' => 1,
			'partial_use' => 1,
			'code' => '',
			'minimum_amount' => 0,
			'minimum_amount_tax' => 0,
			'minimum_amount_currency' => 1,
			'minimum_amount_shipping' => 0,
			'country_restriction' => 0,
			'carrier_restriction' => 0,
			'group_restriction' => 0,
			'cart_rule_restriction' => 0,
			'product_restriction' =>0 ,
			'shop_restriction' => 0,
			'free_shipping' => 0,
			'reduction_percent' => 10,
			'reduction_amount' => 0,
			'reduction_tax' => 0,
			'reduction_currency' => 1,
			'reduction_product' => 0,
			'gift_product' => 0,
			'gift_product_attribute' => 0,
			'highlight' => 1,
			'active' => 1,
			'date_add' => date("Y-m-d H:i:s"),
			'date_upd' => date("Y-m-d H:i:s")
		);

		$this->db->insert('cart_rule', $data);
	}

	/* function order_discount_add($order, $id_cart_rule){
		$data = array(
			'id_cart' => $order;
			'id_cart_rule' => $id_cart_rule;
		);

		$this->db->insert('cart_cart_rule', $data);
	} */


	function get_cart_rule($search, $force = TRUE, $return_all = FALSE){
		$this->db->where('code', $search);
		$this->db->or_where('id_cart_rule', $search);
		if(!$force){
			$this->db
				->group_start()
					->where('date_from <', date("Y-m-d H:i:s"))
					->where('date_to >', date("Y-m-d H:i:s"))
					->where('quantity >', 0)
					->where('active', TRUE)
				->group_end();
		}
		$query = $this->db->get('cart_rule');
		if($query->num_rows() == 1){
			if($return_all){ return $query->row_array(); }
			else{ return $query->row()->id_cart_rule; }
		}else{ return false; }
	}

	function order_messages($order){
		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$this->db->where('id_order', $order);
		$this->db->order_by('date_add', 'DESC');
		$query = $this->db->get('message');

		if($query->num_rows()>0){
			return $query->result_array();
		}
		return array();
	}

	function order_message_add($order, $text, $private = TRUE){
		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$data = [
			'id_order' => $order,
			'id_employee' => 0,
			'message' => $text,
			'private' => $private,
			'date_add' => date("Y-m-d H:i:s"),
		];
		$this->db->insert('message', $data);
		return $this->db->insert_id();
	}

	function order_message_update($order, $text){
		$messages = $this->order_messages($order);
		$found = false;

		if(!empty($messages)){
			foreach($messages as $message){
				if(substr($message['message'], 0, 4) == "APS:"){
				  $found = true;
					$idmess = $message['id_message'];
				}
			}
			if($found){
				$this->db
					->where('id_message', $idmess)
					->set('message', $text)
					->update('message');
			}else{
				$data = [
					'id_cart' => end($messages)['id_cart'],
					'id_customer' => end($messages)['id_customer'],
					'id_employee' => end($messages)['id_employee'],
					'id_order' => end($messages)['id_order'],
					'message' => $text,
					'private' => end($messages)['private'],
					'date_add' => date("Y-m-d H:i:s")
				];

				$this->db->insert('message', $data);
			}
		}
		return true;
	}

	function order_invoice_id($ref, $ret_number = FALSE){
		// En base a referencia de pago, devolver FALSE o número.
		if(!is_numeric($ref)){ $ref = $this->get_order_id($ref); }

		// En la factura de Prestashop se usa el number en vez del ID :S
		$query = $this->db->select(['id_order_invoice', 'number'])->where('id_order', $ref)->get('order_invoice');
		if($query->num_rows() == 1){
			if($ret_number){ return $query->row()->number; }
			else{ return $query->row()->id_order_invoice; }
		}else{ return false; }
	}

	function order_waiting_payment($data){
		// Si es numérico, cojer el estado
		// Si es strlen 9, buscar en el order.

		// 10 - bank
		// 11 - paypal
		$query = $this->db
			->select('current_state')
			->where('reference', $data)
			->or_where('id_order', $data)
		->get('orders');

		if($query->num_rows() == 1){
			$data = $query->row()->current_state;
		}else{
			return false;
		}

		return (in_array($data, [10, 11]));
	}

	function order_full_paid($ref){
		if(is_numeric($ref)){ $ref = $this->get_order_ref($ref); }
		$this->db->where('reference', $ref);
		$query = $this->db->get('orders');

		if($query->num_rows() == 1){
			$order = $query->row();
			if($order->total_paid_real >= $order->total_paid_tax_incl){ return TRUE; }
			if(in_array($order->current_state, [17, 28])){ return TRUE; } // Pago 1 plazo y pago fraccionado completado.
		}
		return FALSE;
	}

	// --------------------------------------------
	//   Customer
	// --------------------------------------------

	function customer($clid, $onlyid = FALSE){
		$select = [
			'id_customer',
			'id_gender',
			'id_lang',
			'firstname',
			'lastname',
			'email',
			// 'passwd',
			'birthday',
			'newsletter',
			'optin',
			'active',
		];
		$this->db
			->select($select)
			->where('id_customer', $clid)
			->or_where('email', $clid);
		$query = $this->db->get('customer');

		if($query->num_rows() == 1){
			if($onlyid){ return $query->row()->id_customer; }
			else{ return $query->row_array(); }
		}
		else{ return false; }
	}

	function customer_addresses($clid){ // Total de direcciones de un cliente.
		$this->db->select('id_address');
		$this->db->where('id_customer', $clid);
		$this->db->order_by('id_address', 'ASC');
		$query = $this->db->get('address');

		if($query->num_rows()>0){ return array_values($query->result_array()); }
		else{ return false; }
	}

	function customer_address($addrid, $inactive = false){
		// id_address	id_country	id_state	id_customer	id_manufacturer	id_supplier	id_warehouse
		// alias	company	lastname	firstname	address1	address2	postcode	city
		// other	phone	phone_mobile	vat_number	dni	date_add	date_upd	active	deleted

		$this->db->where('id_address', $addrid);
		if(!$inactive){ $this->db->where('active', true); } // Buscar solo Direcciones activas.
		$query = $this->db->get('address');

		if($query->num_rows() == 1){
			$client = $query->row_array();
			$country = $this->country($client['id_country']);

			$client['country'] = $country['name'];
			$client['country_iso'] = $country['iso_code'];

			return $client;
		}
	}

	function customer_create($name, $surname, $email, $passwd = NULL, $birthday = NULL, $gender = NULL, $optin = TRUE, $newsletter = TRUE, $groupid = 1, $extra = NULL){

		// Filter and fix email.
		$email = trim(strtolower($email));
		if(filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE){ return FALSE; } // Email no válido.

		// Set gender
		switch (strtolower($gender)) {
			case 'm':
			case 'male':
			case 'hombre':
			case '1':
				$gender = 1;
			break;
			case 'f':
			case 'female':
			case 'mujer':
			case '2':
				$gender = 2;
			default:
				$gender = 0;
			break;
		}

		if(empty($passwd)){ $passwd = 'futbollab'; }
		$passwd = md5($this->cookie_key .$passwd);

		if(empty($birthday)){ $birthday = "0000-00-00"; }
		$birthday = date("Y-m-d", strtotime($birthday));

		$data = array(
			'id_shop_group' => 1,
			'id_shop' => 1,
			'id_gender' => $gender,
			'id_default_group' => $groupid,
			'id_lang' => $this->get_language_code(),
			'firstname' => trim(ucwords(strtolower($name))),
			'lastname' => trim(ucwords(strtolower($surname))),
			'email' => $email,
			'passwd' => $passwd,
			'last_passwd_gen' => date("Y-m-d H:i:s"),
			'birthday' => $birthday,
			'newsletter' => (bool) $newsletter,
			'ip_registration_newsletter' => '0.0.0.0',
			'newsletter_date_add' => ((bool) $newsletter ? date("Y-m-d H:i:s") : "0000-00-00"),
			'optin' => $optin,
			'secure_key' => md5(uniqid(rand(), true)),
			'note' => NULL,
			'active' => TRUE,
			'is_guest' => FALSE,
			'deleted' => FALSE,
			'date_add' => date("Y-m-d H:i:s"),
			'date_upd' => date("Y-m-d H:i:s"),
			'id_risk' => 0,
			'company' => NULL,
			'siret' => NULL,
			'ape' => NULL,
			'website' => NULL,
			'outstanding_allow_amount' => 0,
			'show_public_prices' => 0,
			'max_payment_days' => 0
		);

		$this->db->insert('customer', $data);
		$clid = $this->db->insert_id();

		// Add to customer_groups (default group = 1)
		$this->db->insert('customer_group', ['id_customer' => $clid, 'id_group' => $groupid]);

		// Return registered customer
		return $clid;
	}

	function all_orders(){
		$query = $this->db->get('orders');
		if($query->num_rows()>0){
			return $query->result_array();
		}else{
			return array();
		}
	}

	function customer_orders($data, $returnall = FALSE){
		$customer = $this->customer($data);
		if(empty($customer)){ return FALSE; }

		$query = $this->db
			->where('id_customer', $customer['id_customer'])
			->get('orders');

		if($query->num_rows()>0){
			if($returnall){ return $query->result_array(); }
			else{ return array_column($query->result_array(), 'id_order'); }
		}

		return array();
	}

	// Auto Payment System
	function APS($order){
		$order = $this->order_exists($order, TRUE);
		$order = $this->get_order_id($order);
		if($order === FALSE){ return false; } // No existe pedido!

		$messages = $this->order_messages($order);
		if(empty($messages)){ return false; } // No tiene APS.

		$APS = NULL;
		foreach($messages as $m){
			if(substr($m['message'], 0, 4) == "APS:"){ $APS = $m['message']; break; }
		}

		if(empty($APS)){ return false; }

		$APS = explode(":", $APS);

		$final = [
			'recurring_times' => $APS[1],
			'recurring_terms' => $APS[2],
			'discount' => $APS[3]
		];

		return $final;
	}

	function customer_address_add($clid, $address, $phone = NULL, $name = NULL, $surname = NULL, $extra = NULL){
		$customer = $this->customer($clid);
		if(empty($name)){ $name = $customer['firstname']; }
		if(empty($surname)){ $surname = $customer['lastname']; }

		$name = trim(ucwords(strtolower($name)));
		$surname = trim(ucwords(strtolower($surname)));

		if(!is_array($address)){
			$address = explode(",", $address);
			$addr['address1'] = $address[0] .", " .$address[1];
			$addr['city'] = $address[2];
			$addr['postcode'] = $address[3];
			$addr['country'] = $address[4];
		}else{
			$addr = $address;
		}

		$addr['city'] = trim(ucwords(strtolower($addr['city'])));
		$addr['postcode'] = trim(ucwords(strtolower($addr['postcode'])));
		$addr['country'] = trim(ucwords(strtolower($addr['country'])));

		$country = $this->country($addr['country']);
		if($country !== FALSE){ $addr['country'] = $country['id_country']; }
		else{ $addr['country'] = 6; } // España es el 6.

		$data = array(
			'id_customer' => $clid,
			'alias' => 'Casa',
			'firstname' => $name,
			'lastname' => $surname,
			'id_manufacturer' => 0,
			'id_supplier' => 0,
			'id_warehouse' => 0,
			'company' => '',
			'address1' => $addr['address1'],
			'address2' => '',
			'city' => $addr['city'],
			'postcode' => $addr['postcode'],
			'id_country' => $addr['country'],
			'id_state' => 0,
			'other' => '',
			'phone' => $phone,
			'phone_mobile' => '',
			'vat_number' => '',
			'dni' => '',
			'date_add' => date("Y-m-d H:i:s"),
			'date_upd' => date("Y-m-d H:i:s"),
			'active' => TRUE,
			'deleted' => FALSE,
		);

		if(!empty($extra)){
			foreach ($extra as $k => $v){ $data[$k] = $v; }
		}

		$this->db->insert('address', $data);
		return $this->db->insert_id();
	}

	function country($search, $lang = NULL){
		// id_zone, id_currency, iso_code, call_prefix, active, name
		$lang = $this->get_language_code($lang);

		$this->db->select('*')->from('country')->join('country_lang', 'country.id_country = country_lang.id_country', 'inner');
		$this->db->where('country_lang.id_lang', $lang );
		if(is_numeric($search)){ $this->db->where('country.id_country', $search); }
		elseif(!is_numeric($search) && strlen($search) == 2){ $this->db->where('country.iso_code', $search); }

		$query = $this->db->get();
		if($query->num_rows() == 1){ return $query->row_array(); }
		else{ return false; }
	}

	function countries($lang = NULL){
		$lang = $this->get_language_code($lang);
		$this->db->select('*')->from('country')->join('country_lang', 'country.id_country = country_lang.id_country', 'inner');
		$this->db->where('country_lang.id_lang', $lang );
		$query = $this->db->get();

		if($query->num_rows()>0){ return $query->result_array(); }
		return array();
	}

	function languages($active = true){
		if($active){$this->db->where('active', true);}
		$query = $this->db->get('lang');
		if($query->num_rows()>0){
			foreach($query->result_array() as $lang){
				$language[$lang['id_lang']] = $lang;
			}
			return $language;
		}
	}

	public function language($l = null){ if(!empty($l)){ $this->lang = $l; } else { return $this->lang; } }
	public function get_language_code($iso = NULL, $active = true){ if(empty($iso)){$iso = $this->lang;}foreach($this->languages($active) as $lang){if($lang['iso_code'] == $iso){return $lang['id_lang'];}} }

}
?>
