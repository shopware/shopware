<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

#[Package('framework')]
class LogEntryEntity extends Entity
{
    use EntityIdTrait;

    protected string $message;

    protected int $level;

    protected string $channel;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $context = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $extra = null;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExtra(): ?array
    {
        return $this->extra;
    }

    /**
     * @param array<string, mixed>|null $extra
     */
    public function setExtra(?array $extra): void
    {
        $this->extra = $extra;
    }
}
