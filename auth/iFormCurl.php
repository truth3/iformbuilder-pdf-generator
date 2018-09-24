<?php namespace iForm\Auth;

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
     * @var
     */
    private $ch = null;

    /**
     * error
     *
     * @var null
     */
    private $error = null;

    /**
     * Curl exec
     *
     * @return mixed
     */
    private function execute()
    {
        $results = curl_exec($this->ch);
        $this->error = curl_error($this->ch);
        curl_close($this->ch);

        return $results;
    }


    public function getError()
    {
        return $this->error;
    }

    /**
     * init curl
     */
    private function startResource()
    {
        if (! is_null($this->ch)) return;
        $this->ch = curl_init();
    }

    /**
     * @param string $url
     * @param array  $params
     *
     * @return $this|string
     */
    public function post($url, array $params = null)
    {
        $this->startResource();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);

        if (! is_null($params)) {
            $params = http_build_query($params);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);

            return $this->execute();
        } else {
            return $this;
        }
    }

    /**
     * @param array $params passed to method
     *
     * @throws \Exception
     * @return string
     */
    public function with(array $params)
    {
        if (is_null($this->ch)) {
            throw new \Exception('Invalid use of method.  Must declare request type before passing parameters');
        }
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));

        return $this->execute();
    }

}