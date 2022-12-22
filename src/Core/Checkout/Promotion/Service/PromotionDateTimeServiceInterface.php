<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Service;

/**
 * @package checkout
 */
interface PromotionDateTimeServiceInterface
{
    public function getNow(): string;
}
