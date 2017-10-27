<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Framework\ShopwareException;

class ResponseEnvelope implements \JsonSerializable
{
    /**
     * @var int
     */
    private $total = 0;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var \Exception[]
     */
    private $errors = [];

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @var ShopwareException
     */
    private $exception;

    /**
     * @param mixed $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return \Exception[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getException(): ?ShopwareException
    {
        return $this->exception;
    }

    public function jsonSerialize()
    {
        return [
            'parameters' => $this->getParameters(),
            'exception' => $this->transformExceptionToArray($this->getException()),
            'errors' => $this->getErrors(),
            'total' => $this->getTotal(),
            'data' => $this->getData(),
        ];
    }

    /**
     * @param int $total
     */
    public function setTotal(int $total)
    {
        $this->total = $total;
    }

    /**
     * @param \Exception[] $errors
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function setException(ShopwareException $exception)
    {
        $this->exception = $exception;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    private function transformExceptionToArray(?ShopwareException $exception): ?array
    {
        if (!$exception) {
            return null;
        }

        return [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
    }
}
