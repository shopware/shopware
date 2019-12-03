<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\HttpClient;

class Response
{
    /**
     * @var string
     */
    private $body;

    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $header;

    /**
     * @param string $body
     * @param int    $code
     * @param string $header
     */
    public function __construct($body, $code, $header)
    {
        $this->body = $body;
        $this->code = $code;
        $this->header = $header;
    }

    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }
}
