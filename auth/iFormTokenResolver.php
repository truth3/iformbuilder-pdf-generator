<?php
declare(strict_types=1);

namespace iForm\Auth;

require_once("iFormCurl.php");
require_once("JWT.php");

/**
 * @category Authentication
 * @package  iForm\Authentication
 * @author   Seth Salinas <ssalinas@zerionsoftware.com>
 * @license  http://opensource.org/licenses/MIT
 */
class iFormTokenResolver {
    /**
     * This value has a maximum of 10 minutes
     *
     * @var int
     */
    private static int $exp = 600;
    
    /**
     * Credentials - secret.  See instructions for acquiring credentials
     *
     * @var string
     */
    private string $secret;
    
    /**
     * Credentials - client key.  See instructions for acquiring credentials
     *
     * @var string
     */
    private string $client;
    
    /**
     * oAuth - https://ServerName.iformbuilder.com/exzact/api/oauth/token
     *
     * @var string
     */
    private string $endpoint;
    
    /**
     * HTTP Request handler
     * 
     * @var iFormCurl
     */
    private iFormCurl $request;
    
    /**
     * @param string $url
     * @param string $client
     * @param string $secret
     * @param iFormCurl|null $requester Can pass mock or dummy object for unit testing
     */
    public function __construct(string $url, string $client, string $secret, ?iFormCurl $requester = null)
    {
        $this->client = $client;
        $this->secret = $secret;
        $this->request = $requester ?? new iFormCurl();
        $this->endpoint = trim($url);
    }

    /**
     * @param string $client_key
     * @param string $client_secret
     *
     * @return string
     */
    private function encode(string $client_key, string $client_secret): string
    {
        $iat = time();
        $payload = [
            "iss" => $client_key,
            "aud" => $this->endpoint,
            "exp" => $iat + self::$exp,
            "iat" => $iat
        ];
        return \JWT::encode($payload, $client_secret);
    }
    
    /**
     * api OAuth endpoint
     *
     * @param string $url
     *
     * @return bool
     */
    private function isValid(string $url): bool
    {
        return str_contains($url, "exzact/api/oauth/token");
    }
    
    /**
     * Set endpoint after check
     *
     * @throws \Exception
     * @return void
     */
    private function validateEndpoint(): void
    {
        if (empty($this->endpoint) || !$this->isValid($this->endpoint)) {
            throw new \Exception('Invalid url: Valid format https://SERVER_NAME.iformbuilder.com/exzact/api/oauth/token');
        }
    }
    
    /**
     * Format Params
     *
     * @return array
     */
    private function getParams(): array
    {
        return [
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion" => $this->encode($this->client, $this->secret)
        ];
    }
    
    /**
     * Request/get token
     *
     * @return string
     */
    public function getToken(): string
    {
        try {
            $this->validateEndpoint();
            $params = $this->getParams();
            $response = $this->request->post($this->endpoint)->with($params);
            return $this->check($response);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    
    /**
     * Check results
     * 
     * @param string $results
     *
     * @return string token || error msg
     */
    private function check(string $results): string
    {
        try {
            $token = json_decode($results, true, 512, JSON_THROW_ON_ERROR);
            return $token['access_token'] ?? $token['error'] ?? 'Unknown error';
        } catch (\JsonException $e) {
            return 'Invalid JSON response: ' . $results;
        }
    }
}
