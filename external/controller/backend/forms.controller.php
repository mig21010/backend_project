<?php
class BackendFormsController extends Controller{
	function init(){
		//
	}
	function getSubControllerName($base_name){
		return "BackendForms{$base_name}";
	}
	function indexAction(){
		global $site;
			$request = $site->getRequest();
			$response = $site->getResponse();
			#
			$this->requireUser();
			#
			$dbh = $site->getDatabase();
			#
			$search = $request->param('search', '');
			$show = $request->param('show', 40);
			$page = $request->param('page', 1);
			$search_s = $dbh->quote("%{$search}%");
			#
			$conditions = '1';
			$conditions .= $search ? " AND (name LIKE {$search_s} OR slug LIKE {$search_s})" : '';
			$params = [];
			$params['show'] = $show;
			$params['page'] = $page;
			$params['conditions'] = $conditions;
			$items = Forms::all($params);
			$total = Forms::count($conditions);
		
			$data = [];
			$data['items'] = $items;
			$data['total'] = $total;
			$data['search'] = $search;
			$data['show'] = $show;
			#
			$site->render('backend/forms/page-index', $data);
			return $response->respond();
		
	}
	function newAction(){
		global $site;
		$request = $site->getRequest();
		$response = $site->getResponse();
		$this->requireUser();
		switch ($request->type) {
			case 'get':
				$notice = Flasher::notice();
				$data = [];
				$data['notice'] = $notice;
				$site->render('backend/forms/page-new', $data);
				break;
			case 'post':
				$name = $request->post('name');
				$slug = $request->post('slug');
				$products = $request->post('products');
				$language = $request->post('language');
				$processor = $request->post('processor');
				$currency = $request->post('currency');
				$total = $request->post('total');
				//MetaPost
				$quantity = $request->post('quantity');
				$extra_seats = $request->post('extra_seats');
				$time_to_live = $request->post('time_to_live');
				$subscription = $request->post('subscription');
				$thank_you_page = $request->post('thank_you_page');
				$product_description = $request->post('product_description');
				$product_image = $request->files('product_image');
				$attachment = Attachments::upload($product_image);	
				//creating an object validator and added some rules
				$validator = Validator::newInstance()
				->addRule('name',$name)
				->addRule('slug',$slug)
				->addRule('products',$products)
				->addRule('language',$language)
				->addRule('processor',$processor)
				->addRule('currency',$currency)
				->addRule('total',$total)
				->validate();
				//check the result
				if(! $validator->isValid() ){
					Flasher::notice('The following fields are required: ' . implode(',',$validator->getErrors()));
					$site->redirectTo($site->urlTo('/backend/forms/new'));  
				}
				$form = new Form();
				$form->name = $name;
				$form->slug = $slug;
				$explode = explode(',', $products);
				foreach ($explode as $data) {
					if($data == ' '){
						unset($data);
					}else{
						trim($data);
					}
				}
				$form->products = json_encode($explode);
				$form->language = $language;
				$form->processor = json_encode($processor);
				$form->currency = $currency;
				$form->total = $total;
				$form->save();
				//Saving metas to DB
				$form->updateMeta('quantity', $quantity);
				$form->updateMeta('extra_seats', $extra_seats);
				$form->updateMeta('time_to_live', $time_to_live);
				$form->updateMeta('subscription', $subscription);
				$form->updateMeta('thank_you_page', $thank_you_page);
				$form->updateMeta('product_description', $product_description);
				$form->updateMeta('product_image', $attachment->id);
		  
				$site->redirectTo($site->urlTo('/backend/forms?msg=220'));
			break;
		}
		return $response->respond();
	}
	function editAction($id){
		global $site;
		$request = $site->getRequest();
		$response = $site->getResponse();
		#
		$this->requireUser();
		#
		$params = array();
		$params['pdoargs'] = array('fetch_metas' => 1);
		$form = Forms::getById($id, $params);
		if(!$form) {
			//exit;
			$site->redirectTo( $site->urlTo('/backend/forms/') );
		}
	   
		#
		switch($request->type){
			case 'get':
				$notice = Flasher::notice();
				$data = [];
				$data['item'] = $form;
				$data['notice'] = $notice;
				$site->render('backend/forms/page-edit', $data);
			   
			break;
			case 'post':
				$name = $request->post('name');
				$slug = $request->post('slug');
				$products = $request->post('products');
				$language = $request->post('language');
				$processor = $request->post('processor');
				$currency = $request->post('currency');
				$total = $request->post('total');
				
				//MetaPost
				$quantity = $request->post('quantity');
				$extra_seats = $request->post('extra_seats');
				$time_to_live = $request->post('time_to_live');
				$subscription = $request->post('subscription');
				$thank_you_page = $request->post('thank_you_page');
				$product_description = $request->post('product_description');
				$product_image = $request->files('product_image');
				$attachment = Attachments::upload($product_image);
				//creating an object validator and added some rules
				$validator = Validator::newInstance()
				->addRule('name',$name)
				->addRule('slug',$slug)
				->addRule('products',$products)
				->addRule('language',$language)
				->addRule('processor',$processor)
				->addRule('currency',$currency)
				->addRule('total',$total)
				->validate();
				//check the result
				if(! $validator->isValid() ){
					Flasher::notice('The following fields are required: ' . implode(',',$validator->getErrors()));
					$site->redirectTo($site->urlTo("/backend/forms/edit/{$form->id}")); 
				}

				$explode = explode(',', $products);
				$form->name = $name;
				$form->slug = $slug;
				$form->products = json_encode($explode);
				$form->language = $language;
				$form->processor = json_encode($processor);
				$form->currency = $currency;
				$form->total = $total;
				$form->save();
				//Updating metas to DB
				$form->updateMeta('quantity',$quantity);
				$form->updateMeta('extra_seats', $extra_seats);
				$form->updateMeta('time_to_live', $time_to_live);
				$form->updateMeta('subscription', $subscription);
				$form->updateMeta('thank_you_page', $thank_you_page);
				$form->updateMeta('product_description', $product_description);
				$form->updateMeta('product_image', $attachment->id);

				$site->redirectTo($site->urlTo('/backend/forms?msg=220'));
			break;
		}
		return $response->respond();
	}
	function deleteAction($id){
		global $site;
		$request = $site->getRequest();
		$response = $site->getResponse();

		$form = Forms::getById($id);
		if (! $form){
			$site->redirectTo($site->urlTo('/backend/forms/'));
		}
		switch ($request->type) {
			case 'get':
				$data = array();
				$data['item'] = $form;
				$site->render('backend/forms/page-delete',$data);
			break;
			case 'post':
				$form->delete();
				$site->redirectTo($site->urlTo('/backend/forms?msg=230'));
			 break;
		}
		return $response->respond();
	}
}
?>