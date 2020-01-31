<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Steps;

class ErrorResult
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var \Exception
     */
    private $exception;

    /**
     * @var array
     */
    private $args;

    /**
     * @param string     $message
     * @param \Throwable $exception
     * @param array      $args
     */
    public function __construct($message, ?\Throwable $exception = null, $args = [])
    {
        $this->message = $message;
        $this->exception = $exception;
        $this->args = $args;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
