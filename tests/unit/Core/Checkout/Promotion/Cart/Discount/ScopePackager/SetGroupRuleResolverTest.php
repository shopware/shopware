<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupRuleResolver;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(SetGroupRuleResolver::class)]
#[Package('checkout')]
class SetGroupRuleResolverTest extends TestCase
{
    public function testRuleCollectionPassedThrough(): void
    {
        $collection = new RuleCollection();
        $ruleLoader = static::createStub(AbstractRuleLoader::class);

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['rules' => $collection, 'groupId' => Uuid::randomHex()],
            Context::createDefaultContext(),
        );

        static::assertSame($collection, $result);
    }

    public function testNullRulesReturnsEmptyCollection(): void
    {
        $ruleLoader = static::createStub(AbstractRuleLoader::class);

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['rules' => null, 'groupId' => Uuid::randomHex()],
            Context::createDefaultContext(),
        );

        static::assertCount(0, $result);
    }

    public function testMissingRulesKeyReturnsEmptyCollection(): void
    {
        $ruleLoader = static::createStub(AbstractRuleLoader::class);

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['groupId' => Uuid::randomHex()],
            Context::createDefaultContext(),
        );

        static::assertCount(0, $result);
    }

    public function testEmptyArrayReturnsEmptyCollection(): void
    {
        $ruleLoader = static::createStub(AbstractRuleLoader::class);

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['rules' => [], 'groupId' => Uuid::randomHex()],
            Context::createDefaultContext(),
        );

        static::assertCount(0, $result);
    }

    public function testArrayRulesRehydratedFromRuleLoader(): void
    {
        $ruleId = Uuid::randomHex();

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId($ruleId);
        $ruleEntity->setName('Test Rule');
        $ruleEntity->setUniqueIdentifier($ruleId);

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn(new RuleCollection([$ruleEntity]));

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['rules' => [['id' => $ruleId, 'name' => 'Test Rule']], 'groupId' => Uuid::randomHex()],
            Context::createDefaultContext(),
        );

        static::assertCount(1, $result);
        static::assertSame($ruleId, $result->first()?->getId());
    }

    public function testMultipleArrayRulesRehydrated(): void
    {
        $ruleId1 = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $rule1 = new RuleEntity();
        $rule1->setId($ruleId1);
        $rule1->setName('Rule 1');
        $rule1->setUniqueIdentifier($ruleId1);

        $rule2 = new RuleEntity();
        $rule2->setId($ruleId2);
        $rule2->setName('Rule 2');
        $rule2->setUniqueIdentifier($ruleId2);

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn(new RuleCollection([$rule1, $rule2]));

        $result = (new SetGroupRuleResolver($ruleLoader))->resolve(
            [
                'rules' => [
                    ['id' => $ruleId1, 'name' => 'Rule 1'],
                    ['id' => $ruleId2, 'name' => 'Rule 2'],
                ],
                'groupId' => Uuid::randomHex(),
            ],
            Context::createDefaultContext(),
        );

        static::assertCount(2, $result);
    }

    public function testUnresolvableRuleIdThrows(): void
    {
        $groupId = Uuid::randomHex();

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn(new RuleCollection());

        $this->expectExceptionObject(PromotionException::promotionSetGroupNotFound($groupId));

        (new SetGroupRuleResolver($ruleLoader))->resolve(
            ['rules' => [['id' => Uuid::randomHex()]], 'groupId' => $groupId],
            Context::createDefaultContext(),
        );
    }

    public function testPartiallyUnresolvableRuleIdsThrow(): void
    {
        $ruleId1 = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $rule1 = new RuleEntity();
        $rule1->setId($ruleId1);
        $rule1->setName('Rule 1');
        $rule1->setUniqueIdentifier($ruleId1);

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn(new RuleCollection([$rule1]));

        $this->expectExceptionObject(PromotionException::promotionSetGroupNotFound($groupId));

        (new SetGroupRuleResolver($ruleLoader))->resolve(
            [
                'rules' => [
                    ['id' => $ruleId1, 'name' => 'Rule 1'],
                    ['id' => Uuid::randomHex(), 'name' => 'Deleted Rule'],
                ],
                'groupId' => $groupId,
            ],
            Context::createDefaultContext(),
        );
    }
}
