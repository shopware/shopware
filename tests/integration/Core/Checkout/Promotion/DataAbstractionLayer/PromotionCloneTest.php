<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCloneTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCloneResetsRedemptionCounters(): void
    {
        $context = Context::createDefaultContext();
        $sourceId = Uuid::randomHex();
        $cloneId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $promotionRepository->create([[
            'id' => $sourceId,
            'name' => 'Used promotion',
            'orderCount' => 3,
            'ordersPerCustomerCount' => [$customerId => 2],
        ]], $context);

        $promotionRepository->clone($sourceId, $context, $cloneId);

        $clone = $promotionRepository->search(new Criteria([$cloneId]), $context)->first();
        static::assertInstanceOf(PromotionEntity::class, $clone);
        static::assertSame(0, $clone->getOrderCount());
        static::assertNull($clone->getOrdersPerCustomerCount());
    }
}
