<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Shopware\Core\Checkout\Promotion\Service\PromotionDateTimeServiceInterface;

class FakePromotionDateTimeService implements PromotionDateTimeServiceInterface
{
    public function getNow(): string
    {
        return '2019-05-23 10:00:00';
    }
}
