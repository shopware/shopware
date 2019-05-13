<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Service;

interface PromotionDateTimeServiceInterface
{
    public function getNow(): string;
}
