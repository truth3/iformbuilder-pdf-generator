<?php
declare(strict_types=1);

namespace iForm\Auth;

/**
 * Class iFormCurl
 *
 * @package     iForm\auth
 * @author      Seth Salinas<ssalinas@zerionsoftware.com>
 * @description iFormBuilder Class that uses curl library to make calls against api.  Flexible interface allows for
 *              chained parameters;
 */
class iFormCurl {

    /**
     * start curl object
     *
     * @var \CurlHandle|resource|null
     */
    private $ch = null;

    /**
     * error
     *
     * @var string|null
     */
    private string|null $error = null;

    /**
     * Curl exec
     *
     * @return string
     * @throws \RuntimeException
     */
    private function execute(): string
    {
        $results = curl_exec($this->ch);
        if ($results === false) {
            $this->error = curl_error($this->ch);
            $errno = curl_errno($this->ch);
            curl_close($this->ch);
            throw new \RuntimeException("cURL request failed: $this->error (Error #$errno)");
        }
        
        curl_close($this->ch);
        return $results;
    }

    /**
     * Get the last curl error
     * 
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Initialize curl resource
     * 
     * @throws \RuntimeException
     * @return void
     */
    private function startResource(): void
    {
        if (!is_null($this->ch)) return;
        
        $this->ch = curl_init();
        if ($this->ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }
    }

    /**
     * Prepare a POST request
     *
     * @param string $url
     * @param array|null $params
     *
     * @return $this|string
     * @throws \RuntimeException
     */
    public function post(string $url, ?array $params = null): mixed
    {
        $this->startResource();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, ['Content-type: application/x-www-form-urlencoded']);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);

        if ($params !== null) {
            $encodedParams = http_build_query($params);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $encodedParams);
            return $this->execute();
        } else {
            return $this;
        }
    }

    /**
     * Add parameters to an existing request
     *
     * @param array $params passed to method
     *
     * @return string
     * @throws \Exception
     */
    public function with(array $params): string
    {
        if (is_null($this->ch)) {
            throw new \Exception('Invalid use of method. Must declare request type before passing parameters');
        }
        
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));
        return $this->execute();
    }
}
