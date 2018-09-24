<?php namespace iForm\Auth;

require_once("iFormCurl.php");

require_once("JWT.php");
use iForm\Auth\iFormCurl;
/**
 * @category Authentication
 * @package  iForm\Authentication
 * @author   Seth Salinas <ssalinas@zerionsoftware.com>
 * @license  http://opensource.org/licenses/MIT
 */
class ZIMTokenResolver {
    /**
     * This value has a maximum of 10 minutes
     *
     * @var int
     */
    private static $exp = 600;
    /**
     * Credentials - secret.  See instructions for acquiring credentials
     *
     * @var string
     */
    private $secret;
    /**
     * Credentials - client key.  See instructions for acquiring credentials
     *
     * @var string
     */
    private $client;
    /**
     * oAuth - https://ServerName.iformbuilder.com/exzact/api/oauth/token
     *
     * @var string
     */
    private $endpoint;
    /**
     * @param string $url
     * @param string $client
     * @param string $secret
     * @param null   $requester Can pass mock or dummy object for unit testing
     *
     * @throws \Exception
     */
    function __construct($url, $client, $secret, $requester = null)
    {
        $this->client = $client;
        $this->secret = $secret;
        $this->request = $requester ?: new iFormCurl();
        $this->endpoint = trim($url);
    }

    /**
     * @param string $client_key
     * @param string $client_secret
     *
     * @return string
     */

    private function encode($client_key, $client_secret)
    {
        $iat = time();
        $payload = array(
            "iss" => $client_key,
            "aud" => $this->endpoint,
            "exp" => $iat + self::$exp,
            "iat" => $iat
        );
        return \JWT::encode($payload, $client_secret);
    }
    /**
     * api OAuth endpoint
     *
     * @param string $url
     *
     * @return boolen
     */
    private function isValid($url)
    {
        return strpos($url, "/oauth2/token") !== false;
    }
    /**
     * Set endpoint after check
     *
     * @param string $url
     *
     * @throws \Exception
     * @return null
     */
    private function validateEndpoint()
    {
        if (empty($this->endpoint) || ! $this->isValid($this->endpoint)) {
            throw new \Exception('Invalid url: Valid format https://SERVER_NAME.zerionsoftware.com/zim/oauth/token');
        }
    }
    /**
     * Format Params
     *
     * @return string
     */
    private function getParams()
    {
        return array("grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
                     "assertion"  => $this->encode($this->client, $this->secret));
    }
    /**
     * Request/get token
     *
     * @return string
     */
    public function getToken()
    {
        try {
            $this->validateEndpoint();
            $params = $this->getParams();
            $result = $this->check($this->request->post($this->endpoint)
                                                 ->with($params));

        } catch (Exception $e){
            $result = $e->getMessage();
        }

        return $result;
    }
    /**
     * Check results
     * @param $results
     *
     * @return string token || error msg
     */
    private function check($results)
    {
        $token = json_decode($results, true);

        return isset($token['access_token']) ? $token['access_token'] : $token['error'];
    }
}
