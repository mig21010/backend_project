<?php

	class HubSpotConnector extends PaymentsConnector {

		function process($order) {
			global $site;

			$credentials = array();
			$credentials['api_key'] = 'a32d646d-4819-4c40-89a9-dd9140ae9fda';

			$hubspot = HubSpot::newInstance($credentials);

			$form = PaymentsForms::getById( $order->getMeta('form') );
			$sku = $form ? json_decode($form->products) : null;
			$sku = $sku ? $sku : [0];

			$skus = implode(', ', $sku);

			$installments = $order->getMeta('installments', 0);
			$concept = $order->getMeta('concept', '');

			# Upsert a contact
			# First name, Last name, Email, Phone, Company
			$properties = array();
			$properties['email'] = $order->getMeta('email');
			$properties['firstname'] = $order->getMeta('first_name');
			$properties['lastname'] = $order->getMeta('last_name');
			$properties['company'] = $order->getMeta('company');
			$properties['phone'] = $order->getMeta('phone');
			$res = $hubspot->contactsUpsert($properties['email'], $properties);

			# Create the deal
			# Price, Product Name, SKU (If aplicable), Number of Installments (if applicable)
			if ($res) {
				$properties = array();
				$properties['dealname'] = "#{$order->id} {$concept} ({$skus})" . ($installments ? " - {$installments} Installments" : '');
				$properties['dealstage'] = 'closedwon';
				$properties['dealtype'] = 'newbusiness';
				$properties['created_from_platform'] = true;
				$properties['sku'] = $sku[0];
				$properties['closedate'] = strtotime($order->payment_date) * 1000;
				$properties['amount'] = $order->total;
				$properties['pipeline'] = 'default';
				$associations = array();
				$associations['associatedVids'] = array($res->vid);
				$res = $hubspot->dealsCreate($properties, $associations);
			}
		}
	}

?>