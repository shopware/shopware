<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageCollection;

class ScheduledTaskEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $scheduledTaskClass;

    /**
     * @var int
     */
    protected $runInterval;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var DeadMessageCollection|null
     */
    protected $deadMessages;

    /**
     * @var \DateTime|null
     */
    protected $lastExecutionTime;

    /**
     * @var \DateTime
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDeadMessages(): ?DeadMessageCollection
    {
        return $this->deadMessages;
    }

    public function setDeadMessages(?DeadMessageCollection $deadMessages): void
    {
        $this->deadMessages = $deadMessages;
    }

    public function getLastExecutionTime(): ?\DateTime
    {
        return $this->lastExecutionTime;
    }

    public function setLastExecutionTime(?\DateTime $lastExecutionTime): void
    {
        $this->lastExecutionTime = $lastExecutionTime;
    }

    public function getNextExecutionTime(): \DateTime
    {
        return $this->nextExecutionTime;
    }

    public function setNextExecutionTime(\DateTime $nextExecutionTime): void
    {
        $this->nextExecutionTime = $nextExecutionTime;
    }
}
