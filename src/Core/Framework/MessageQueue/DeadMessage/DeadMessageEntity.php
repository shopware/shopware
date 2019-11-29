<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

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
     *
     * @internal
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
     * @var \DateTimeInterface
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
     * @var string|null
     */
    protected $scheduledTaskId;

    /**
     * @var ScheduledTaskEntity|null
     */
    protected $scheduledTask;

    public static function calculateNextExecutionTime(int $errorCount): \DateTimeInterface
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

    public function getNextExecutionTime(): \DateTimeInterface
    {
        return $this->nextExecutionTime;
    }

    public function setNextExecutionTime(\DateTimeInterface $nextExecutionTime): void
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

    public function getScheduledTaskId(): ?string
    {
        return $this->scheduledTaskId;
    }

    public function setScheduledTaskId(?string $scheduledTaskId): void
    {
        $this->scheduledTaskId = $scheduledTaskId;
    }

    public function getScheduledTask(): ?ScheduledTaskEntity
    {
        return $this->scheduledTask;
    }

    public function setScheduledTask(?ScheduledTaskEntity $scheduledTask): void
    {
        $this->scheduledTask = $scheduledTask;
    }
}
