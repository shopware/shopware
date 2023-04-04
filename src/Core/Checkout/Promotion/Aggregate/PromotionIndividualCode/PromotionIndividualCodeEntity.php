<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode;

use Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionIndividualCodeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $promotionId;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var PromotionEntity|null
     */
    protected $promotion;

    /**
     * @var array<string>|null
     */
    protected $payload;

    /**
     * Gets if the code has been redeemed
     * and used in an order.
     */
    public function isRedeemed(): bool
    {
        return true;
    }

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(?PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }

    /**
     * @return array<string>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string>|null $payload
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * Sets the code to the state "redeemed" by building
     * a payload and assigning the provided values.
     *
     * @param string $orderId      order that has been placed with this code
     * @param string $customerId   the customer id of the order
     * @param string $customerName the full name of the customer when placing the order
     *
     * @throws CodeAlreadyRedeemedException
     */
    public function setRedeemed(string $orderId, string $customerId, string $customerName): void
    {
        // check if we even have data
        if ($this->payload !== null && \array_key_exists('orderId', $this->payload)) {
            // if we have another order id, then throw an exception
            if ($this->payload['orderId'] !== $orderId) {
                if (Feature::isActive('v6.6.0.0')) {
                    throw PromotionException::codeAlreadyRedeemed($this->code);
                }

                throw new CodeAlreadyRedeemedException($this->code);
            }
        }

        $this->payload = [
            'orderId' => $orderId,
            'customerId' => $customerId,
            'customerName' => $customerName,
        ];
    }
}
