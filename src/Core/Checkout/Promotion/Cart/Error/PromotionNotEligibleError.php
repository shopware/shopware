<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionNotEligibleError extends Error
{
    private const KEY = 'promotion-not-eligible';

    /**
     * @param list<string> $ruleIds Condition rule entity IDs to enable rule-specific snippet lookup
     * @param bool $persistent Whether the error survives cart recalculation (true for once-raised
     *                         collector errors, false for calculator errors re-evaluated each pass)
     */
    public function __construct(
        protected string $name,
        private readonly ?string $reason = null,
        private readonly array $ruleIds = [],
        private readonly bool $persistent = false
    ) {
        $this->message = \sprintf('Promotion %s not eligible for cart!', $this->name);

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function getMessageKey(): string
    {
        return $this->reason !== null ? self::KEY . '-' . $this->reason : self::KEY;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    /**
     * @return list<string>
     */
    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }
}
