<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionCartDeletedInformationError extends Error
{
    private const KEY = 'promotion-discount-deleted';

    private string $name;

    private readonly string $discountLineItemId;

    public function __construct(LineItem $discountLineItem)
    {
        $this->name = $discountLineItem->getLabel();
        $this->discountLineItemId = $discountLineItem->getId();
        $this->message = sprintf(
            'Discount %s has been added',
            $this->name
        );
        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParameters(): array
    {
        return [
            'name' => $this->name,
            'discountLineItemId' => $this->discountLineItemId,
        ];
    }

    public function getId(): string
    {
        return sprintf('%s-%s', self::KEY, $this->discountLineItemId);
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function blockOrder(): bool
    {
        return false;
    }
}
