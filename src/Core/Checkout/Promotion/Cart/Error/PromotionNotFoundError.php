<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionNotFoundError extends Error
{
    private const KEY = 'promotion-not-found';

    /**
     * @var string
     */
    protected $promotionCode;

    public function __construct(string $promotionCode)
    {
        $this->promotionCode = $promotionCode;

        $this->message = sprintf('Promotion with code %s not found!', $this->promotionCode);

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            'code' => $this->promotionCode,
        ];
    }
}
