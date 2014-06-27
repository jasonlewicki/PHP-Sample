<?php

// Sub Controller Class
class IndexController extends MasterController {

	// Constructor
	public function __construct($request_origin, $uri_arr, $locale_arr, $resource_slug) {
		parent::__construct($request_origin, $uri_arr, $locale_arr, $resource_slug);
	}

	// Process the GET request
	protected function processGet() {	
		$this->populateResponseBase();
		return;
	}

	// Process the POST request
	protected function processPost() {
		$this -> populateError(405, 'Allow: GET, OPTIONS, HEAD');
		return;
	}

	// Process the PUT request
	protected function processPut() {
		$this -> populateError(405, 'Allow: GET, OPTIONS, HEAD');
		return;
	}

	// Process the DELETE request
	protected function processDelete() {
		$this -> populateError(405, 'Allow: GET, OPTIONS, HEAD');
		return;
	}

	// Process the OPTIONS request
	protected function processOptions() {
		$this -> populateError(200, 'Allow: GET, OPTIONS, HEAD');
		return;
	}

	// Process the HEAD request
	protected function processHead() {
		$this -> populateError(200);
		return;
	}

}
