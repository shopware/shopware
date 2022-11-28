<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

/**
 * @package checkout
 */
class GenericCartError extends Error
{
    protected string $id;

    protected string $messageKey;

    protected int $level;

    protected bool $blockOrder;

    protected bool $persistent;

    protected array $parameters;

    public function __construct(string $id, string $messageKey, array $parameters, int $level, bool $blockOrder, bool $persistent)
    {
        parent::__construct();
        $this->id = $id;
        $this->messageKey = $messageKey;
        $this->level = $level;
        $this->blockOrder = $blockOrder;
        $this->persistent = $persistent;
        $this->parameters = $parameters;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function blockOrder(): bool
    {
        return $this->blockOrder;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRoute(): ?ErrorRoute
    {
        return null;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }
}
