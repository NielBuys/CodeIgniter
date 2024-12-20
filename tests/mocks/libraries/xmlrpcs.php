<?php

class Mock_Libraries_Xmlrpcs extends CI_Xmlrpcs {
	public $mock_payload = '';

	/**
	 * Act as a XML-RPC server, but save the response into $mock_public property instead of making output of it
	 */
	public function serve()
	{
		$r = $this->parseRequest();
	
		if (isset($r->method_name) && isset($this->config['functions'][$r->method_name])) {
			$callback = $this->config['functions'][$r->method_name]['function'];
			if (is_callable($callback)) {
				call_user_func($callback, $r->parameters);
			} else {
				throw new Exception('Invalid callback: ' . $callback);
			}
		}
	
		$payload = '<?xml version="1.0" encoding="' . $this->xmlrpc_defencoding . '"?>' . "\n" . 
				   $this->debug_msg . $r->prepare_response();
	
		$this->mock_payload = "HTTP/1.1 200 OK\r\n";
		$this->mock_payload .= "Content-Type: text/xml\r\n";
		$this->mock_payload .= 'Content-Length: ' . strlen($payload) . "\r\n";
		$this->mock_payload .= "\r\n";
		$this->mock_payload .= $payload;
	}

    /**
     * Mock XML request (example)
     */
    public function xml_request()
    {
        // Return a mock XML request
		return '<?xml version="1.0"?>
        <methodCall>
            <methodName>Testmethod</methodName>
            <params>
                <param>
                    <value>
                        <string>Test</string>
                    </value>
                </param>
            </params>
        </methodCall>';
    }
}
