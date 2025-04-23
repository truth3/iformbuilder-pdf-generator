<?php

declare(strict_types=1);

namespace iForm\Auth;

require_once("iFormCurl.php");
require_once("JWT.php");

/**
 * ZIMTokenResolver - Handles authentication via JWT and OAuth token retrieval
 * 
 * @category Authentication
 * @package  iForm\Authentication
 * @author   Seth Salinas <ssalinas@zerionsoftware.com>
 * @license  http://opensource.org/licenses/MIT
 */
class ZIMTokenResolver
{
    /**
     * Maximum token expiration time in seconds (10 minutes)
     */
    private const MAX_EXPIRATION = 600;

    /**
     * Authentication endpoint URL
     */
    private readonly string $endpoint;

    /**
     * HTTP request handler
     */
    private readonly iFormCurl $request;

    /**
     * Initialize token resolver with required credentials
     * 
     * @param string $url OAuth endpoint URL
     * @param string $client Client key identifier
     * @param string $secret Client secret
     * @param ?iFormCurl $requester Optional requester for testing
     * 
     * @throws \Exception If parameters are invalid
     */
    public function __construct(
        string $url,
        private readonly string $client,
        private readonly string $secret,
        ?iFormCurl $requester = null
    ) {
        $this->endpoint = trim($url);
        $this->request = $requester ?? new iFormCurl();
    }

    /**
     * Generate JWT encoded assertion
     * 
     * @return string The encoded JWT assertion
     */
    private function generateAssertion(): string
    {
        $iat = time();
        $payload = [
            "iss" => $this->client,
            "aud" => $this->endpoint,
            "exp" => $iat + self::MAX_EXPIRATION,
            "iat" => $iat
        ];
        
        return \JWT::encode($payload, $this->secret);
    }

    /**
     * Validate if URL is a proper OAuth endpoint
     * 
     * @param string $url The URL to validate
     * @return bool True if valid OAuth endpoint
     */
    private function isValidEndpoint(string $url): bool
    {
        return str_contains($url, "/oauth2/token");
    }

    /**
     * Validate the endpoint URL
     * 
     * @throws \Exception If endpoint URL is invalid
     */
    private function validateEndpoint(): void
    {
        if (empty($this->endpoint) || !$this->isValidEndpoint($this->endpoint)) {
            throw new \Exception('Invalid url: Valid format https://SERVER_NAME.zerionsoftware.com/zim/oauth/token');
        }
    }

    /**
     * Get OAuth request parameters
     * 
     * @return array<string, string> OAuth parameters
     */
    private function getRequestParams(): array
    {
        return [
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion"  => $this->generateAssertion()
        ];
    }

    /**
     * Request and get the authentication token
     * 
     * @return string The access token or error message
     */
    public function getToken(): string
    {
        try {
            $this->validateEndpoint();
            $params = $this->getRequestParams();
            $response = $this->request->post($this->endpoint)->with($params);
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Parse and extract token from response
     * 
     * @param string $response The API response
     * @return string Access token or error message
     */
    private function parseResponse(string $response): string
    {
        $data = json_decode($response, true) ?? [];
        
        return $data['access_token'] ?? $data['error'] ?? 'Unknown response format';
    }
}
