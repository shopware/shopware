<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testAnUnusedPromotionCanBeDeleted(): void
    {
        $this->createPromotion(orderCount: 0);

        static::getContainer()->get('promotion.repository')->delete([['id' => $this->ids->get('promotion')]], Context::createDefaultContext());

        $this->expectNotToPerformAssertions();
    }

    public function testAPromotionUsedInAnOrderCannotBeDeleted(): void
    {
        $this->createPromotion(orderCount: 1);

        $this->expectExceptionObject(PromotionException::promotionUsedDeleteRestriction());

        static::getContainer()->get('promotion.repository')->delete([['id' => $this->ids->get('promotion')]], Context::createDefaultContext());
    }

    private function createPromotion(int $orderCount): void
    {
        static::getContainer()->get('promotion.repository')->create([[
            'id' => $this->ids->get('promotion'),
            'name' => 'promotion',
            'active' => true,
            'useCodes' => false,
            'orderCount' => $orderCount,
        ]], Context::createDefaultContext());
    }
}
