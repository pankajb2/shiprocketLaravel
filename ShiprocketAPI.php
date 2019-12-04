<?php
namespace App\Library\Services;
/**
 * Class ShiprocketAPI used to connect ot any Shiprocket App
 */
class ShiprocketAPI
{
	private $_API = array();
	private static $_KEYS = array('EMAIL','PASSWORD','API_URL','ACCESS_TOKEN');
	private static $_TOKEN;


	/**
	 * Checks for presence of setup $data array and loads
	 * @param bool $data
	 */
	public function __construct($data = FALSE)
	{
		if (is_array($data))
		{
			$this->setup($data);
		}
	}

	/**
	 * Calls API and returns OAuth Access Token, which will be needed for all future requests
	 * @param string $code
	 * @return mixed
	 * @throws \Exception
	 */
	public function getAccessToken($code = '')
	{
		$data = $this->call(['METHOD' => 'POST', 'URL' => 'https://' . $this->_API['API_URL'] . '/v1/external/auth/login','DATA' => ['email' => $this->_API['EMAIL'],'password' => $this->_API['PASSWORD']]], FALSE);
		if(is_array($data)){
			return false;
		} else {
			$this->_API['ACCESS_TOKEN']= $data->token;
			return true;
		}

	}

	/**
	 * Loops over each of self::$_KEYS, filters provided data, and loads into $this->_API
	 * @param array $data
	 */
	public function setup($data = array())
	{

		foreach (self::$_KEYS as $k)
		{
			if (array_key_exists($k, $data))
			{
				$this->_API[$k] = self::verifySetup($k, $data[$k]);
			}
		}
	}

	/**
	 * Checks that data provided is in proper format
	 * @example Removes http(s):// from API_URL
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	private static function verifySetup($key = '', $value = '')
	{
		$value = trim($value);

		switch ($key)
		{

			case 'API_URL':
				preg_match('/(https?:\/\/)?([a-zA-Z0-9\-\.])+/', $value, $matched);
				return $matched[0];
				break;

			default:
				return $value;
		}
	}

	/**
	 * Checks that data provided is in proper format
	 * @example Checks for presence of /admin/ in URL
	 * @param array $userData
	 * @return array
	 */
	private function setupUserData($userData = array())
	{
		$returnable = array();

		foreach($userData as $key => $value)
		{
			switch($key)
			{
				case 'URL':
					// Remove shop domain
					$url = str_replace($this->_API['API_URL'], '', $value);


					$returnable[$key] = $url;
					break;

				default:
					$returnable[$key] = $value;

			}
		}

		return $returnable;
	}


	/**
	 * Executes the actual cURL call based on $userData
	 * @param array $userData
	 * @return mixed
	 * @throws \Exception
	 */
    public function call($userData = array(), $verifyData = TRUE)
    {
	    if ($verifyData)
	    {
		    foreach (self::$_KEYS as $k)
		    {
			    if ((!array_key_exists($k, $this->_API)) || (empty($this->_API[$k])))
			    {
				    throw new \Exception($k . ' must be set.');
			    }
		    }
	    }

	    $defaults = array(
		    'CHARSET'       => 'UTF-8',
		    'METHOD'        => 'GET',
		    'URL'           => '/',
			'HEADERS'       => array(),
	        'DATA'          => array(),
	        'FAILONERROR'   => TRUE,
	        'RETURNARRAY'   => FALSE,
	        'ALLDATA'       => FALSE
	    );

	    if ($verifyData)
	    {
		    $request = $this->setupUserData(array_merge($defaults, $userData));
	    }
	    else
	    {
		    $request = array_merge($defaults, $userData);
	    }
	    // Send & accept JSON data
	    $defaultHeaders = array();
	    $defaultHeaders[] = 'Content-Type: application/json; charset=' . $request['CHARSET'];
	    $defaultHeaders[] = 'Accept: application/json';
	    if (array_key_exists('ACCESS_TOKEN', $this->_API))
	    {
		    $defaultHeaders[] = 'Authorization:Bearer ' . $this->_API['ACCESS_TOKEN'];
	    }
        $headers = array_merge($defaultHeaders, $request['HEADERS']);

	    if ($verifyData)
	    {
		    $url = 'https://' . $this->_API['API_URL'] . $request['URL'];
	    }
	    else
	    {
		    $url = $request['URL'];
	    }
	    // cURL setup
        $ch = curl_init();
        $options = array(
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_URL             => $url,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_CUSTOMREQUEST   => strtoupper($request['METHOD']),
            CURLOPT_ENCODING        => '',
            CURLOPT_USERAGENT       => 'Holisol WMS',
            CURLOPT_FAILONERROR     => $request['FAILONERROR'],
            CURLOPT_VERBOSE         => $request['ALLDATA'],
            CURLOPT_HEADER          => 1
        );
	    // Checks if DATA is being sent
	    if (!empty($request['DATA']))
	    {
		    if (is_array($request['DATA']))
		    {
			    $options[CURLOPT_POSTFIELDS] = json_encode($request['DATA']);
		    }
		    else
		    {
			    // Detect if already a JSON object
			    json_decode($request['DATA']);
			    if (json_last_error() == JSON_ERROR_NONE)
			    {
				    $options[CURLOPT_POSTFIELDS] = $request['DATA'];
			    }
			    else
			    {
				    throw new \Exception('DATA malformed.');
			    }
		    }
	    } else {
			if(strtoupper($request['METHOD'])=="POST")
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['DATA']));
		}
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // Data returned
        $result = json_decode(substr($response, $headerSize), $request['RETURNARRAY']);
        // Headers
        $info = array_filter(array_map('trim', explode("\n", substr($response, 0, $headerSize))));
        foreach($info as $k => $header)
        {
	        if (strpos($header, 'HTTP/') > -1)
	        {
                $_INFO['HTTP_CODE'] = $header;
                continue;
            }

            list($key, $val) = explode(':', $header);
            $_INFO[trim($key)] = trim($val);
        }


        // cURL Errors
        $_ERROR = array('NUMBER' => curl_errno($ch), 'MESSAGE' => curl_error($ch));
        curl_close($ch);
	    if ($_ERROR['NUMBER'])
	    {
			$result['_ERROR'] = $_ERROR;
			return $result;
		   // throw new \Exception('ERROR #' . $_ERROR['NUMBER'] . ': ' . $_ERROR['MESSAGE']);
	    }
	    // Send back in format that user requested
	    if ($request['ALLDATA'])
	    {
		    if ($request['RETURNARRAY'])
		    {
			    $result['_ERROR'] = $_ERROR;
			    $result['_INFO'] = $_INFO;
		    }
		    else
		    {
			    $result->_ERROR = $_ERROR;
			    $result->_INFO = $_INFO;
		    }
		    return $result;
	    }
	    else
	    {
		    return $result;
	    }


    }

} // End of API class