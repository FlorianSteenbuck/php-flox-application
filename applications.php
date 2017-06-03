<?php
/*
MIT License

Copyright (c) 2017 Florian Steenbuck

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

class Errors {
	public static function error_404($path) {
		$resp = array();
		$resp["ok"] = 1;
		$resp["error"] = array();
		$resp["error"]["guilty"] = "programmer";
		$resp["error"]["name"] = "404 Not Found";
		$resp["error"]["msg"] = "Cannot Found '".$path."'";
		$resp["error"]["code"] = 404;

		return $resp;
	} 
}

class RequestHandler
{
	public $path_parts = [];
	public $priority = -1;
	protected $application = null;

	function __construct($path_parts, $priority)
	{
		self::$path_parts = $path_parts;
		self::$priority = $priority;
	}

	function set_application($application) {
		self::$application = $application;
	}

	function get($path_parts, $args, $headers) {
		return null;
	}

	function post($path_parts, $args, $headers) {
		return null;
	}
}

class RespRequestHandler extends RequestHandler
{
	function get($path_parts, $args, $headers) {
		return self::resp($path_parts, $args, $headers);
	}

	function post($path_parts, $args, $headers) {
		return self::resp($path_parts, $args, $headers);
	}

	function resp($path_parts, $args, $headers) {
		return null;
	}
}

class MultiLanguageHandler extends RespRequestHandler
{
	protected $language =  "text/plain";

	function switch_language($language) {
		self::$language = $language;
	}
}

class CookieJar
{
	public $cookies = array();

	function __construct($cookies)
	{
		self::$cookies = $cookies;
	}

	function add($name, $value) {
		self::$cookies[$name] = $value;
	}

	function get() {
		return $cookies;
	}

	function merge($cookiejar) {
		foreach ($cookiejar as $key => $value) {
			self::add($key, $value);
		}
	}
}

class LimaCityLoginHandler
{
	
	function __construct()
	{
		parent::__construct(["login"], 1);
	}
}

class LanguageUnsupported extends Exception {
	protected $message = "Selected Language is unsupported";
	protected $code = 9401;
}

class MultiArrayLanguageHandler extends MultiLanguageHandler
{
	protected $language =  "text/plain";
	protected $supported_languages = [];

	function switch_language($language) {
		if (!in_array($language, $supported_languages)) {
			throw new LanguageUnsupported("Selected Language '".$language."' is unsupported");
		}
		parent::switch_language($language);
	}
}

class NotFound404Handler extends MultiArrayLanguageHandler
{
	protected $supported_languages = ["text/plain", "application/json", "text/html"];
	
	function resp($path_parts, $args, $headers) {
		header("HTTP/1.0 404 Not Found");
		$path = join('/', $array);
		switch (self::$language) {
			case $supported_languages[0]:
				self::resp_plain($path);
				break;
			
			case $supported_languages[1]:
				self::resp_json($path);
				break;
			
			case $supported_languages[2]:
				self::resp_html($path);
				break;
		}
	}

	function resp_json($path) {
		return json_encode(Errors::error_404($path));
	}

	function resp_plain($path) {
		$resp = Errors::error_404($path);
		header("Error Code: ".$resp["error"]["code"]);
		header("Guilty: ".$resp["error"]["guilty"]);
		return $resp["error"]["name"].":".$resp["error"]["msg"];
	}

	function resp_html($path) {
		$resp = Errors::error_404($path);
		$html = "<h1>";
		$html .= $resp["error"]["name"]."</h1>";
		$html .= "<p>".$resp["error"]["msg"]."</p>";
		$html .= "Error Code: ".$resp["error"]["code"]." - Guilty: ".$resp["error"]["guilty"];
		return $html;
	}
}

class Application
{
	protected $handlers = [];
	protected $language = "text/plain";
	function __construct($handlers, $language)
	{
		self::$language = $language;
		foreach ($handlers as $handler) {
			addHandler($handler);
		}
	}

	function addHandler($handler) {
		array_push(self::$handlers, $handler);
	}

	protected function matchPathParts($req_path_parts, $given_path_parts) {
		$given_path_parts_count = count($given_path_parts);
		if (count($req_path_parts) < $given_path_parts_count){
			return false;
		}

		for ($i=0; $i < $given_path_parts_count; $i++) { 
			if ($given_path_parts[$i] !== $req_path_parts[$i]) {
				return false;
			}
		}

		return true;
	}

	protected function getMatchedHandlers() {
		$matched_handlers = [];
		foreach (self::$handlers as $handler) {
			if (self::matchPathParts($path_parts, $handler->path_parts)) {
				array_push($matched_handlers, $handler);
			}
		}

		return $matched_handlers;
	}

	protected function getHandler($path_parts) {
		$priority_handler = null;
		foreach (self::getMatchedHandlers() as $handler) {
			if ($priority_handler === null || $priority_handler->priority > $handler->priority) {
				$priority_handler = $handler;
			}
		}

		if ($priority_handler === null) {
			$not_found = new NotFound404Handler($path_parts, -1);
			try {
				$not_found->switch_language("application/json");
				return $not_found;
			} catch (LanguageUnsupported $ex) {
				return null;
			}
		}

		return $priority_handler;
	}

	protected function mine_type_header() {
		header("Content-Type: ".self::$language);
	}

	function get($path_parts, $args, $headers) {
		mine_type_header();
		return self::getHandler($path_parts)->get($path_parts, $args, $headers);
	}

	function post($path_parts, $args, $headers) {
		mine_type_header();
		return self::getHandler($path_parts)->post($path_parts, $args, $headers);
	}
}

class GatewayApplication extends Application
{
	function gateway_call($args)
	{
		return null;
	}

	protected function getHandler($path_parts) {
		$handler = parent::getHandler($path_parts);
		$handler->set_application(self);
		return $handler;
	}
}

function is_int_key_array($array) {
	if (!is_array($array)) {
		return false;
	}

	foreach ($array as $key => $value) {
		if (!is_int($key)) {
			return false;
		}
	}

	return true;
}

function is_raw_file($file) {
	return is_array($file) &&
		!is_int_key_array($file) &&
		array_key_exists("name", $file) &&
		array_key_exists("filename", $file) &&
		array_key_exists("content", $file);
}

function get_all_urls($string) {
	if (!is_string($string)) {
		return [];
	}
	preg_match_all('#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $string, $result);
	return $result;
}

function parse_file_format($format, $value, $placeholder='[$]') {
	$pos_a = strpos($format, $placeholder);
	if ($pos_a == -1) {
		return $format . $value;
	}
	$pos_b = $pos_a + (count($value) - 1);

	return ($format.substr($format, $pos_a)) . $value . ($format.substr($format, 0, $pos_b));
}

function get_files_from_multi($multi_files, $field_format="file") {
	if (is_array($multi_files)) {
		$files = [];
		foreach ($multi_files as $multi_file) {
			$files = array_merge($files, get_files_from_multi($multi_file));
		}
	} elseif (is_raw_file($multi_files)) {
		return [$multi_files];
	}

	$raw_files = array();
	$id = 0;
	foreach (get_all_urls($multi_files) as $url) {
		$content = file_get_contents($url);
		if ($content === null) {
			continue;
		}

		$url_parts = explode('/', $url);
		$offset = strlen($url_parts) == 0 ? 0 : -1;

		$filename = $url_part[strlen($url_parts) + $offset];
		$name = parse_file_format($field_format, $id);

		$raw_file = array();
		$raw_file["name"] = $name;
		$raw_file["filename"] = $filename;
		$raw_file["content"] = $content;

		array_push($raw_files, $raw_file);

		$id++;
	}

	return $raw_files;
}

class CurlGatewayApplication extends Application
{
	protected $base_url = "http://127.0.0.1";
	protected $auto_data_fields_to_form_fields = true;

	protected function get_curl_conf($data=null, $data_type=null, $files=null) {
		$config = array();
		$headers = array();

		$last_data = $data;
		$last_data_needed = true;

		$files = get_files_from_multi($files);

		$are_there_files = is_array($files) || count($files) > 0;

		$data = '';
		$boundary = '';
		if ($files !== null) {
			$boundary = hash('sha256', uniqid('', true));
    		$delimiter = '-------------' . $boundary;

    		if ($auto_data_fields_to_form_fields && !is_int_key_array($last_data)) {
    			$last_data_needed = false;
    			foreach ($last_data as $name => $content) {
        			$data .= "--" . $delimiter . "\r\n"
            			. 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
            			. $content . "\r\n";
    			}
    		}

    		foreach ($files as $file) {
        		$data .= "--" . $delimiter . "\r\n"
        		    . 'Content-Disposition: form-data; name="' . $file["name"] . '"; filename="' . $file["filename"] . '"' . "\r\n\r\n"
        		    . $file["content"] . "\r\n";
    		}
	    	$data .= "--" . $delimiter . "--\r\n";
    	}

    	if ($last_data !== null && $last_data_needed) {
    		$data .= $last_data;
    	}

		$config[CURLOPT_RETURNTRANSFER] = true;
		$config[CURLOPT_HEADER] = true;
		$config[CURLOPT_VERBOSE] = true;
		if ($data !== '') {
			$config[CURLOPT_POST] = true;
			$config[CURLOPT_POSTFIELDS] = $data;
			$headers[] = 'Content-Length: ' . strlen($data);

			$content_type = null;
			if ($data_type !== null) {
				$content_type = 'Content-Type: '.$data_type;
			} else if ($are_there_files) {
				$content_type = 'Content-Type: multipart/form-data';
			}

			if ($are_there_files) {
				$content_type .= '; boundary=' . $delimiter;
			}

			if ($content_type !== null) {
				$headers[] = $content_type;
			}
		}

		$config[CURLOPT_HTTPHEADER] = $headers;
		return $config;
	}

	function curl_call($path, $data=null, $data_type=null, $files=null) {
		$args = array();
		$args["path"] = $path;
		$args["curl"] = array();
		$args["curl"]["data"] = $data;
		$args["curl"]["data_type"] = $data_type;
		$args["curl"]["files"] = $files;

		return gateway_call($args);
	}

	function gateway_call($args)
	{
		if (array_key_exists("curl", $args)) {
			$curl_args = $args["curl"];

			$curl_data = null;
			$curl_data_type = null;
			$curl_files = null;

			$curl = curl_init($base_url . "/" . $args["path"]);
			if (array_key_exists("data", $curl_args)) {
				$curl_data = $curl_args["data"];
			}

			if (array_key_exists("data_type", $curl_args)) {
				$curl_data_type = $curl_args["data_type"];
			}

			if (array_key_exists("files", $curl_args)) {
				$curl_files = $curl_args["files"];
			}
			curl_setopt_array($curl, get_curl_conf($curl_data, $curl_data_type, $curl_files));
			$result = curl_exec($curl);
			
			$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
			$status = ["HTTP/1.0", "200", "OK"];
			$last_key = null;
			$header_text = substr($response, 0, $header_size);
			foreach (explode("\r\n", $header_text) as $i => $line) {
        		if ($i === 0) {
            		$status = explode(' ', $line);
        		} else {
            		$args = explode(': ', $line);
            		if (count($args) > 1) {
            			$last_key = $args[0];
            			$headers[$args[0]] = substr($args, strlen($args[0]));
            		} else if ($last_key !== null) {
            			// pseudo support for multiple line headers
            			$headers[$last_key] .= $line;
            		}
        		}
        	}
			$body = substr($response, $header_size);
			
			curl_close($curl);

			return [$status, $header, $body];
		}
		return null;
	}
}


class CookieJarCurlGatewayApplication extends GatewayApplication
{
	protected $cookiejar = null;

	function __construct($handlers, $language) {
		self::$cookiejar = new CookieJar([]);
		parent::__construct($handlers, $language);
	}

	function curl_call($path, $data=null, $data_type=null, $files=null) {
		$resp_args = parent::curl_call($path, $data, $data_type, $files);
		$headers = $resp_args[1];
		$cookies = array();
		foreach ($headers as $key => $value) {
			if (strtolower($key) == "set-cookie") {
				$raw_cookies = explode(',', $value);
				foreach ($raw_cookies as $raw_cookie) {
					if ($raw_cookie[0] == ' ') {
						$raw_cookie = substr($raw_cookie, 1);
					}

					$raw_key_value = explode(';', $raw_cookie)[0];
					if ($raw_key_value[0] == ' ') {
						$raw_key_value = substr($raw_key_value, 1);
					}
					$key_val_args = explode('=', $raw_key_value);
					if (count($key_val_args) > 1) {
						$key = $key_val_args[0];
						$value = substr($raw_key_value, strlen($key));
						$cookies[$key] = $value;
					}
				}
			}
		}

		self::$cookiejar->merge(new CookieJar($cookies));

		return $resp_args;
	}
}

?>