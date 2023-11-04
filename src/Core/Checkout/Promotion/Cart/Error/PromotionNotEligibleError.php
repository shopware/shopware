<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionNotEligibleError extends Error
{
    private const KEY = 'promotion-not-eligible';

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;

        $this->message = sprintf('Promotion %s not eligible for cart!', $this->name);

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return false;
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
        return self::KEY;
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
}
