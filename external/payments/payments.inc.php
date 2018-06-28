<?php

	# Include models
	include $site->baseDir('/external/model/payments/form.model.php');
	include $site->baseDir('/external/model/payments/order.model.php');
	include $site->baseDir('/external/model/payments/order-items.model.php');

	# Include components
	include $site->baseDir('/external/payments/cart.inc.php');
	include $site->baseDir('/external/payments/connector.inc.php');
	include $site->baseDir('/external/payments/processor.inc.php');
	include $site->baseDir('/external/payments/connector/docebo.connector.php');
	include $site->baseDir('/external/payments/connector/hubspot.connector.php');
	include $site->baseDir('/external/payments/connector/hummingbird.connector.php');
	include $site->baseDir('/external/payments/processor/conekta.processor.php');
	include $site->baseDir('/external/payments/processor/paypal.processor.php');
	include $site->baseDir('/external/payments/processor/stripe.processor.php');

	class Payments {

		private static $instance;

		public $connectors;
		public $cart;

		public static function getInstance() {
			if (null === static::$instance) {
				static::$instance = new static();
			}
			return static::$instance;
		}

		protected function __construct() {
			global $site;
			#
			$this->connectors = [];
			$this->cart = PaymentsCart::getInstance();
			# Register routes
			$site->getRouter()->addRoute('/form/:form', 'Payments::routeForm');
			$site->getRouter()->addRoute('/review/:uid', 'Payments::routeReview');
			$site->getRouter()->addRoute('/thanks/:uid', 'Payments::routeThanks');
			$site->getRouter()->addRoute('/:processor/webhook', 'Payments::routeWebhook');
			$site->getRouter()->addRoute('/:processor/charge/:uid', 'Payments::routeCharge');
		}

		static function routeForm($args) {
			global $site;
			#
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$params = [];
			$params['pdoargs'] = ['fetch_metas'];
			$req_form = get_item($args, 1);
			$form = is_numeric($req_form) ? PaymentsForms::getById($req_form, $params) : PaymentsForms::getBySlug($req_form, $params);
			if ($form) {
				#
				switch ($request->type) {
					case 'get':
						# Initialize cart
						$products_json = json_decode($form->products);
						if ($products_json) {
							foreach ($products_json as $sku) {
								$product = new PaymentsCartItem();
								$product->name = $sku;
								$site->payments->cart->addItem($sku, $product, true);
							}
						}
						# Save or update the order
						$order = PaymentsOrders::getByUid($site->payments->cart->uid);
						if (! $order ) {
							$order = new PaymentsOrder();
							$order->uid = $site->payments->cart->uid;
							$order->total = $form->total;
							$order->currency = $form->currency;
							$order->save();
							$order->updateMeta('form', $form->id);
							$order->updateMeta('concept', $form->name);
						} else {
							if ($order->payment_status == 'Paid') {
								$site->payments->cart->reset();
								$site->redirectTo( $site->urlTo("/form/{$form->slug}") );
							} else {
								$order->total = $form->total;
								$order->currency = $form->currency;
								$order->save();
								$order->updateMeta('form', $form->id);
								$order->updateMeta('concept', $form->name);
							}
						}
						#
						$data = [];
						$data['form'] = $form;
						$data['order'] = $order;
						$site->setPageTitle( $site->getPageTitle($form->name) );
						$site->render('payments/page-form', $data);
					break;
					case 'post':
						$first_name = $request->post('first_name');
						$last_name = $request->post('last_name');
						$email = $request->post('email');
						$phone = $request->post('phone');
						$company = $request->post('company');
						#
						$order = PaymentsOrders::getByUid($site->payments->cart->uid);
						$order->updateMeta('first_name', $first_name);
						$order->updateMeta('last_name', $last_name);
						$order->updateMeta('email', $email);
						$order->updateMeta('phone', $phone);
						$order->updateMeta('company', $company);
						#
						$site->redirectTo( $site->urlTo("/review/{$order->uid}") );
					break;
				}
			} else {
				$site->redirectTo( $site->urlTo('/error') );
			}
			return $response->respond();
		}

		static function routeReview($args) {
			global $site;
			#
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$req_order = get_item($args, 1);
			$order = PaymentsOrders::getByUid($req_order);
			$params = [];
			$params['pdoargs'] = ['fetch_metas'];
			$form = PaymentsForms::getById($order->getMeta('form', 0), $params);
			#
			if ($order && $form) {
				if ($order->payment_status == 'Pending') {
					switch ($request->type) {
						case 'get':
							#
							$processors_json = json_decode($form->processor);
							$processors = [];
							if ($processors_json) {
								foreach ($processors_json as $processor) {
									$processor_class = "{$processor}Processor";
									$processors[$processor] = new $processor_class;
									$processors[$processor]->includeDependencies($form);
								}
							}
							#
							$data = [];
							$data['form'] = $form;
							$data['order'] = $order;
							$data['processors'] = $processors;
							$site->setPageTitle( $site->getPageTitle($form->name) );
							$site->render('payments/page-review', $data);
						break;
					}
				} else {
					$site->payments->cart->reset();
					$site->redirectTo( $site->urlTo("/form/{$form->slug}") );
				}
			} else {
				$site->redirectTo( $site->urlTo('/error') );
			}
			#
			return $response->respond();
		}

		static function routeCharge($args) {
			global $site;
			#
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$req_processor = get_item($args, 2);
			$processor_class = "{$req_processor}Processor";
			$processor_class = ucfirst($processor_class);
			if ( class_exists($processor_class) ) {
				$processor = new $processor_class;
				switch ($request->type) {
					case 'get':
						$site->redirectTo( $site->urlTo('/error') );
					break;
					case 'post':
						$fields = $request->post();
						$order = PaymentsOrders::getByUid( $fields['custom'] );
						if ($order) {
							$processor->process($order, $fields);
						} else {
							$site->redirectTo( $site->urlTo('/error') );
						}
					break;
				}
			} else {
				$site->redirectTo( $site->urlTo('/error') );
			}
			return $response->respond();
		}

		static function routeWebhook($args) {
			global $site;
			#
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$req_processor = get_item($args, 1);
			$processor_class = "{$req_processor}Processor";
			$processor_class = ucfirst($processor_class);
			if ( class_exists($processor_class) ) {
				$processor = new $processor_class;
				switch ($request->type) {
					case 'get':
						$site->redirectTo( $site->urlTo('/error') );
					break;
					case 'post':
						$fields = $request->post();
						$processor->webhook($fields);
					break;
				}
			} else {
				$site->redirectTo( $site->urlTo('/error') );
			}
			return $response->respond();
		}

		static function routeThanks($args) {
			global $site;
			#
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$req_order = get_item($args, 1);
			$order = PaymentsOrders::getByUid($req_order);
			if ($order) {
				#
				switch ($request->type) {
					case 'get':
					case 'post':
						$data = [];
						$data['order'] = $order;
						$site->setPageTitle( $site->getPageTitle('Thank you') );
						$site->render('payments/page-thanks', $data);
					break;
				}
			} else {
				$site->redirectTo( $site->urlTo('/error') );
			}
			return $response->respond();
		}

		function notifyProcessed($order) {
			global $site;
			if ($this->connectors) {
				foreach ($this->connectors as $name => $instance) {
					$instance->process($order);
				}
			}
		}

		function enableConnector($name, $instance) {
			$this->connectors[$name] = $instance;
		}

		function disableConnector($name) {
			unset( $this->connectors[$name] );
		}

		function getExpiryDate($expr) {
			$ret = '';
			$functions = array(
				array('fn' => 'Payments::fnExpiryTTL', 'regex' => '/^timeToLive(Days|Months|Years)\((\d+)\)$/'),
				array('fn' => 'Payments::fnExpiryDate', 'regex' => '/^dateSet\([\'\"](\d{2})\/(\d{2})\/(\d{4})[\'\"]\)$/')
			);
			foreach ($functions as $function) {
				if ( preg_match($function['regex'], $expr, $matches) === 1 ) {
					array_shift($matches);
					$ret = call_user_func($function['fn'], $matches);
					break;
				}
			}
			return $ret;
		}

		protected static function fnExpiryTTL($params) {
			$ret = '';
			$period = get_item($params, 0, 'Days');
			$amount = get_item($params, 1, 30);
			$expr = sprintf('+%d %s', $amount, $period);
			$ret = date('Y-m-d 23:59:59', strtotime($expr));
			return $ret;
		}

		protected static function fnExpiryDate($params) {
			$ret = '';
			$year = get_item($params, 2);
			$month = get_item($params, 1);
			$day = get_item($params, 0);
			$ret = "{$year}/{$month}/{$day} 23:59:59";
			return $ret;
		}

		private function __clone() {}
		private function __wakeup() {}
	}

	$site->payments = Payments::getInstance();

?>