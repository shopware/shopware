<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class PurchaseStepsError extends Error
{
    /**
     * @var string
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $id;

    /**
     * @var string
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $name;

    /**
     * @var int
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $quantity;

    public function __construct(
        string $id,
        string $name,
        int $quantity
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;

        $this->message = sprintf(
            'Your input quantity does not match with the setup of the %s. The quantity was changed to %d',
            $name,
            $quantity
        );

        parent::__construct($this->message);
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'quantity' => $this->quantity];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'purchase-steps-quantity';
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
