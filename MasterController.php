<?php

// Class for the Master Controller
abstract class MasterController {

	protected $model_obj;
	protected $response;
	protected $uri_arr;
	protected $action;
	protected $page_title_suffix;

	// Constructor
	protected function __construct($request_origin, $uri_arr, $locale_arr, $resource_slug) {
		$this -> model_obj = null;
		$this -> response = array();
		$this -> uri_arr = $uri_arr;
		$this -> page_title_suffix = " | ProjectName";
		
		// Get request method
		$request_method = strtoupper($_SERVER['REQUEST_METHOD']);

		if ($this -> initializeMasterModel($request_origin, $request_method, $uri_arr, $locale_arr, $resource_slug)) {
			if ($request_origin == 'site') {
				$this -> userAuthorizeSite();
				$this -> localeProcess();
			} else if ($request_origin == 'api') {
				$this -> userAuthorizeAPI();
			}
			$this -> permissionGet();
			$this -> processRequest($request_method);
		}
		$this -> sendResponse();
	}

	// Force Extending classes to define these methods
	abstract protected function processGet();
	abstract protected function processPost();
	abstract protected function processPut();
	abstract protected function processDelete();
	abstract protected function processOptions();
	abstract protected function processHead();

	// Initialize the proper Master Model depending on the first URI element
	protected function initializeMasterModel($request_origin, $request_method, $uri_arr, $locale_arr, $resource_slug) {
		// Get data from specific request method
		$data_arr = $this -> getRequestData($request_method);
		if ($data_arr === false)
			return false;

		// Make sure the correct data structure was sent
		$http_accept = (strpos($_SERVER['HTTP_ACCEPT'], 'xml')) ? 'xml' : 'json';

		if (!$this -> validateHTTPAccept($http_accept))
			return false;

		// Include base model
		require_once (str_replace('public/', '', $_SERVER['DOCUMENT_ROOT']) . 'private/model/MasterModel.php');
		$this -> model_obj = new MasterModel($request_origin, $request_method, $data_arr, $uri_arr, $locale_arr, $resource_slug);

		return true;
	}

	// Gather the data submitted depending on what method was used
	protected function getRequestData($request_method) {

		// Store request data here
		$data_arr = array();
		// Sift through the cases to find which request method was used
		switch ($request_method) {
			case 'GET' :
				$data_arr = $_GET;
				break;
			case 'POST' :
				$data_arr = $_POST;
				break;
			case 'PUT' :
				parse_str(file_get_contents('php://input'), $data_arr);
				break;
			case 'DELETE' :				
				parse_str(file_get_contents('php://input'), $data_arr);
				break;
			case 'HEAD' :
				break;
			case 'OPTIONS' :
				break;
			default :
				$this -> response = $this -> populateError(400, "Invalid request method. Only GET, POST, PUT, DELETE, HEAD, and OPTIONS are allowed");
				return false;
		}

		if (array_key_exists('action', $data_arr)) {
			$this -> action = $data_arr['action'];
			unset($data_arr['action']);
		}
		if (array_key_exists('data', $data_arr))
			return $data_arr['data'];
		return $data_arr;
	}

	// Process the request from the Controller
	protected function processRequest($request_method) {

		// Make sure that the user/guest has permission to execute the given request
		if (($status = $this -> model_obj -> permissionVerify()) === true) {

			// Sift through the cases to find which request method was used
			switch ($request_method) {
				case 'GET' :
					$this -> processGet();
					break;
				case 'POST' :
					$this -> processPost();
					break;
				case 'PUT' :
					$this -> processPut();
					break;
				case 'DELETE' :
					$this -> processDelete();
					break;
				case 'HEAD' :
					$this -> processHead();
					break;
				case 'OPTIONS' :
					$this -> processOptions();
					break;
			}
		} else {
			$this -> populateError($status);
		}
	}

	// Function that checks if the submitted data is in the correct format
	protected function validateHTTPAccept($http_accept) {

		// Check if the type of data submitted is correct
		if ($http_accept != "json") {
			$this -> response = $this -> populateError(415, "The server supports these formats: JSON");
			return false;
		}

		return true;
	}

	// Authorize user by checking Cookies/SHA
	protected function userAuthorizeSite() {
		$this -> model_obj -> userAuthorizeSite();
	}

	// Authorize user by checking Tokens
	protected function userAuthorizeAPI() {
		$this -> model_obj -> userAuthorizeAPI();
	}

	// Process locale from URI
	protected function localeProcess() {
		$this -> model_obj -> localeProcess();
	}

	// Get user permission to a specific page
	protected function permissionGet() {
		$this -> model_obj -> permissionGet();
	}

	// Populate Error message
	protected function populateError($status, $message = null) {

		if ($message == null)
			$message = getStatusCodeMessage($status);

		header($message, true, $status);

		$response = array();
		$response['status'] = $status;
		$response['message'] = $message;

		return $response;
	}
	
	// Populate Response with Status information
	protected function populateResponseStatus(){
		$this->response['status'] 			= $this->model_obj->responseGetStatus();
		$this->response['success_arr'] 		= $this->model_obj->responseGetSuccessArr();
		$this->response['error_arr'] 		= $this->model_obj->responseGetErrorArr();
		$this->response['data_arr'] 		= $this->model_obj->responseGetDataArr();
	}
	
	// Populate Response with Base information
	protected function populateResponseBase(){

		$this->response['action'] 			= $this->action;
		$this->response['user'] 			= $this->model_obj->userGetSanitized();
		$this->response['locale'] 			= $this->model_obj->localeGet();
		$this->response['language_arr'] 	= $this->model_obj->localeListLanguageByCountryIdAndStatus('enabled');
		$this->response['country_arr'] 		= $this->model_obj->localeListCountryByStatus('enabled');
		$this->response['resource'] 		= $this->model_obj->getResourceSlug();
		$this->response['resource_arr'] 	= $this->model_obj->resourceListByUserType();
		$this->response['resource_text_arr']= $this->model_obj->resourceGetText();
		$this->response['title'] 			= $this->response['resource_text_arr']['title'] . $this -> page_title_suffix;
		$this->response['href']				= "/".$this->response['locale']['locale_country']['locale_country_slug']."/".$this->response['locale']['locale_language']['locale_language_slug'];
		
		// Append resource slug to href if it is not 'index
		if($this->response['resource'] != 'index'){
			$this->response['href']			.= '/'.$this->response['resource'];
		}
		
		// If GET request, append query string to the end of href
		if(strtoupper($_SERVER['REQUEST_METHOD']) == 'GET'){
			// Get $_GET variable array
			$get_arr = $this->model_obj->getDataArr();
			
			// Ignore history token
			unset($get_arr['_']);
			
			// Unset IE Cache variable
			unset($get_arr['__']);
			
			// Set get array for response
			$this->response['uri_arr'] = $get_arr;
			
			// Build GET string up
			$get_string = http_build_query($get_arr);
			 
			// Append string to HREF that will be returned
			if(strlen($get_string) > 0){
				$this->response['href'] .= '?'.$get_string;
			}			
		}		
	} 

	// Send response
	protected function sendResponse() {
		header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header('Content-type: application/json');
		echo json_encode($this -> response);
	}

}

// HTTP Status Codes array
function getStatusCodeMessage($status) {
	//HTTP status codes
	$codes = Array(
		100 => 'Continue', 
		101 => 'Switching Protocols', 
		200 => 'OK', 
		201 => 'Created', 
		202 => 'Accepted', 
		203 => 'Non-Authoritative Information', 
		204 => 'No Content', 
		205 => 'Reset Content', 
		206 => 'Partial Content', 
		300 => 'Multiple Choices', 
		301 => 'Moved Permanently', 
		302 => 'Found', 
		303 => 'See Other', 
		304 => 'Not Modified', 
		305 => 'Use Proxy', 
		306 => '(Unused)', 
		307 => 'Temporary Redirect', 
		400 => 'Bad Request', 
		401 => 'Unauthorized', 
		402 => 'Payment Required', 
		403 => 'Forbidden', 
		404 => 'Not Found', 
		405 => 'Method Not Allowed', 
		406 => 'Not Acceptable', 
		407 => 'Proxy Authentication Required', 
		408 => 'Request Timeout', 
		409 => 'Conflict', 
		410 => 'Gone', 
		411 => 'Length Required', 
		412 => 'Precondition Failed', 
		413 => 'Request Entity Too Large', 
		414 => 'Request-URI Too Long', 
		415 => 'Unsupported Media Type', 
		416 => 'Requested Range Not Satisfiable', 
		417 => 'Expectation Failed', 
		422 => 'Unprocessable Entity', 
		500 => 'Internal Server Error', 
		501 => 'Not Implemented', 
		502 => 'Bad Gateway', 
		503 => 'Service Unavailable', 
		504 => 'Gateway Timeout', 
		505 => 'HTTP Version Not Supported'
	);

	return (isset($codes[$status])) ? $codes[$status] : '';
}

function __autoload($class_name) {
	include str_replace('public/', '', $_SERVER['DOCUMENT_ROOT']) . "private/" . $class_name . '.php';
}

//secure flag for cookie once https is enabled
// login to POST /sessions controller, DELETE /sessions/{key}
//$_SERVER["HTTP_ACCEPT_LANGUAGE"]
//post return data, when feasible
//get return data
//JS camelcase
//timestamps ISO 8601
//PUT CREATED LOCATION HEADER
//POST just status
//pagination ?offset=50&limit=25 put a max result in, say 100

/*
 * 200 OK
 * {
 * 	"href":"/accounts/sds3r/groups",
 * "offset":0,
 * "limit":25,
 * "first":{"href":"/accounts/sds3r/groups?offset=0"},
 * "previous":null,
 * "next":{"href":"/accounts/sds3r/groups?offset=25"},
 * "last":{"href":"/accounts/sds3r/groups?offset=10124"},
 * "items":[
 * 	{
 * 		"href": "..."
 * 	},
 * 	{
 * 		"href": "..."
 * 	}
 * 	]
 * }
 */
