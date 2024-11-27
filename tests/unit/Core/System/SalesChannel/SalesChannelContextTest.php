<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SalesChannelContext::class)]
class SalesChannelContextTest extends TestCase
{
    public function testGetRuleIdsByAreas(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();
        $idD = Uuid::randomHex();

        $areaRuleIds = [
            'a' => [$idA, $idB],
            'b' => [$idA, $idC, $idD],
            'c' => [$idB],
            'd' => [$idC],
        ];

        $salesChannelContext->setAreaRuleIds($areaRuleIds);

        static::assertEquals($areaRuleIds, $salesChannelContext->getAreaRuleIds());

        static::assertEquals([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a']));
        static::assertEquals([$idA, $idB, $idC, $idD], $salesChannelContext->getRuleIdsByAreas(['a', 'b']));
        static::assertEquals([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a', 'c']));
        static::assertEquals([$idC], $salesChannelContext->getRuleIdsByAreas(['d']));
        static::assertEquals([], $salesChannelContext->getRuleIdsByAreas(['f']));
    }
}
