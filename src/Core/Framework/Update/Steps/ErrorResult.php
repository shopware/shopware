<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

class ErrorResult
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var \Throwable
     */
    private $exception;

    /**
     * @var array
     */
    private $args;

    public function __construct(string $message, ?\Throwable $exception = null, array $args = [])
    {
        $this->message = $message;
        $this->exception = $exception;
        $this->args = $args;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
