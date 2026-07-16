<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupRuleResolver;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(SetScopeDiscountPackager::class)]
#[Package('checkout')]
class SetScopeDiscountPackagerTest extends TestCase
{
    public function testGroupDefinitionsBuiltFromPayload(): void
    {
        $ruleCollection = new RuleCollection();

        $ruleResolver = static::createStub(SetGroupRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn($ruleCollection);

        $builder = static::createStub(LineItemGroupBuilder::class);
        $builder
            ->method('findGroupPackages')
            ->willReturnCallback(static function (array $groupDefinitions) use ($ruleCollection) {
                static::assertCount(2, $groupDefinitions);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[0]);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[1]);
                static::assertSame($ruleCollection, $groupDefinitions[0]->getRules());
                static::assertSame('COUNT', $groupDefinitions[0]->getPackagerKey());
                static::assertSame(5.0, $groupDefinitions[0]->getValue());

                return new LineItemGroupBuilderResult();
            });

        $payload = [
            'discountScope' => 'set',
            'discountType' => 'absolute',
            'setGroups' => [
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'COUNT',
                    'value' => 5,
                    'sorterKey' => 'PRICE_ASC',
                    'rules' => [['id' => Uuid::randomHex()]],
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'COUNT',
                    'value' => 3,
                    'sorterKey' => 'PRICE_ASC',
                    'rules' => $ruleCollection,
                ],
            ],
        ];

        (new SetScopeDiscountPackager($builder, $ruleResolver))->getMatchingItems(
            new DiscountLineItem('label', new AbsolutePriceDefinition(10), $payload, null),
            new Cart('token'),
            static::createStub(SalesChannelContext::class),
        );
    }
}
