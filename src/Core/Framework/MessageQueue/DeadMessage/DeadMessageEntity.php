<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DeadMessageEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $originalMessageClass;

    /**
     * @var string
     */
    protected $serializedOriginalMessage;

    /**
     * @var object
     */
    protected $originalMessage;
    /**
     * @var string
     */
    protected $handlerClass;

    /**
     * @var bool
     */
    protected $encrypted;

    /**
     * @var \DateTime
     */
    protected $nextExecutionTime;

    /**
     * @var string
     */
    protected $exception;

    /**
     * @var string
     */
    protected $exceptionMessage;

    /**
     * @var string
     */
    protected $exceptionFile;

    /**
     * @var int
     */
    protected $exceptionLine;

    /**
     * @var int
     */
    protected $errorCount;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    public static function calculateNextExecutionTime(int $errorCount): \DateTime
    {
        return (new \DateTime())->modify(sprintf('+%d seconds', $errorCount ** 2));
    }

    public function getOriginalMessageClass(): string
    {
        return $this->originalMessageClass;
    }

    public function setOriginalMessageClass(string $originalMessageClass): void
    {
        $this->originalMessageClass = $originalMessageClass;
    }

    public function getSerializedOriginalMessage(): string
    {
        return $this->serializedOriginalMessage;
    }

    public function setSerializedOriginalMessage(string $serializedOriginalMessage): void
    {
        $this->serializedOriginalMessage = $serializedOriginalMessage;
    }

    public function getOriginalMessage(): object
    {
        return $this->originalMessage;
    }

    public function setOriginalMessage(object $originalMessage): void
    {
        $this->originalMessage = $originalMessage;
    }

    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    public function setHandlerClass(string $handlerClass): void
    {
        $this->handlerClass = $handlerClass;
    }

    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    public function setEncrypted(bool $encrypted): void
    {
        $this->encrypted = $encrypted;
    }

    public function getNextExecutionTime(): \DateTime
    {
        return $this->nextExecutionTime;
    }

    public function setNextExecutionTime(\DateTime $nextExecutionTime): void
    {
        $this->nextExecutionTime = $nextExecutionTime;
    }

    public function getException(): string
    {
        return $this->exception;
    }

    public function setException(string $exception): void
    {
        $this->exception = $exception;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function setExceptionMessage(string $exceptionMessage): void
    {
        $this->exceptionMessage = $exceptionMessage;
    }

    public function getExceptionFile(): string
    {
        return $this->exceptionFile;
    }

    public function setExceptionFile(string $exceptionFile): void
    {
        $this->exceptionFile = $exceptionFile;
    }

    public function getExceptionLine(): int
    {
        return $this->exceptionLine;
    }

    public function setExceptionLine(int $exceptionLine): void
    {
        $this->exceptionLine = $exceptionLine;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): void
    {
        $this->errorCount = $errorCount;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
