<?php

namespace Shopware\Api;

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

    public function jsonSerialize()
    {
        return [
            'parameters' => $this->getParameters(),
            'errors' => $this->getErrors(),
            'total' => $this->getTotal(),
            'data' => $this->getData(),
        ];
    }

    /**
     * @param \Exception[] $errors
     *
     * @return array
     */
    private function transformExceptionsToErrors(array $errors): array
    {
        $arrayExceptions = [];

        foreach ($errors as $error) {
            $arrayExceptions[] = [
                'type' => get_class($error),
                'message' => $error->getMessage(),
                'trace' => $error->getTraceAsString()
            ];
        }

        return $arrayExceptions;
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

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }
}