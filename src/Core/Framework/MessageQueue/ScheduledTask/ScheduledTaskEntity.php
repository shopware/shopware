<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ScheduledTaskEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $scheduledTaskClass;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $runInterval;

    protected int $defaultRunInterval;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $status;

    /**
     * @var \DateTimeInterface|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $lastExecutionTime;

    /**
     * @var \DateTimeInterface
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $nextExecutionTime;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getScheduledTaskClass(): string
    {
        return $this->scheduledTaskClass;
    }

    public function setScheduledTaskClass(string $scheduledTaskClass): void
    {
        $this->scheduledTaskClass = $scheduledTaskClass;
    }

    public function getRunInterval(): int
    {
        return $this->runInterval;
    }

    public function setRunInterval(int $runInterval): void
    {
        $this->runInterval = $runInterval;
    }

    public function getDefaultRunInterval(): int
    {
        return $this->defaultRunInterval;
    }

    public function setDefaultRunInterval(int $defaultRunInterval): void
    {
        $this->defaultRunInterval = $defaultRunInterval;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isExecutionAllowed(): bool
    {
        // If the status is failed, skipped or queued, the execution is still allowed, so retries are possible.
        // To ensure idempotency, even allow execution if the task is currently running.
        // The messenger transport must ensure no concurrent execution happens.
        return $this->status === ScheduledTaskDefinition::STATUS_QUEUED
            || $this->status === ScheduledTaskDefinition::STATUS_FAILED
            || $this->status === ScheduledTaskDefinition::STATUS_SKIPPED
            || $this->status === ScheduledTaskDefinition::STATUS_RUNNING;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getLastExecutionTime(): ?\DateTimeInterface
    {
        return $this->lastExecutionTime;
    }

    public function setLastExecutionTime(?\DateTimeInterface $lastExecutionTime): void
    {
        $this->lastExecutionTime = $lastExecutionTime;
    }

    public function getNextExecutionTime(): \DateTimeInterface
    {
        return $this->nextExecutionTime;
    }

    public function setNextExecutionTime(\DateTimeInterface $nextExecutionTime): void
    {
        $this->nextExecutionTime = $nextExecutionTime;
    }
}
