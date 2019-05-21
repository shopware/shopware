<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LogEntryEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var string|null
     */
    protected $content;

    /**
     * @var string|null
     */
    protected $extra;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setExtra(?string $extra): void
    {
        $this->extra = $extra;
    }
}
