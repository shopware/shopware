<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function testGetIdsByArea(): void
    {
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['b', 'c']);

        $ruleC = new RuleEntity();
        $ruleC->setId(Uuid::randomHex());
        $ruleC->setAreas(['c']);

        $ruleD = new RuleEntity();
        $ruleD->setId(Uuid::randomHex());

        $ruleE = new RuleEntity();
        $ruleE->setId(Uuid::randomHex());
        $ruleE->setAreas(['a', 'd']);

        $collection = new RuleCollection([$ruleA, $ruleB, $ruleC, $ruleD, $ruleE]);

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleE->getId()],
            'b' => [$ruleA->getId(), $ruleB->getId()],
            'c' => [$ruleB->getId(), $ruleC->getId()],
            'd' => [$ruleE->getId()],
        ], $collection->getIdsByArea());
    }

    public function testGetIdsByAreaDeduplicatesAndKeepsInsertionOrder(): void
    {
        // a rule listing the same area twice must not produce a duplicate id
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['a']);

        $collection = new RuleCollection([$ruleA, $ruleB]);

        $result = $collection->getIdsByArea();

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleB->getId()],
            'b' => [$ruleA->getId()],
        ], $result);

        // the returned id lists must be sequentially indexed (real lists, no gaps)
        static::assertSame([0, 1], array_keys($result['a']));
    }
}
