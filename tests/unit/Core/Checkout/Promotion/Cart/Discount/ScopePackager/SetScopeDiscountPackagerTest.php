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
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetScopeDiscountPackager::class)]
class SetScopeDiscountPackagerTest extends TestCase
{
    public function testGroupDefinitionsBuiltFromPayload(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleCollection = new RuleCollection();

        $builder = static::createStub(LineItemGroupBuilder::class);
        $builder
            ->method('findGroupPackages')
            ->willReturnCallback(static function (array $groupDefinitions) use ($ruleCollection, $ruleId) {
                static::assertCount(2, $groupDefinitions);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[0]);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[1]);
                static::assertSame($ruleId, $groupDefinitions[0]->getRules()->first()?->getId());
                static::assertSame($ruleCollection, $groupDefinitions[1]->getRules());
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
                    'rules' => [['id' => $ruleId]],
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

        (new SetScopeDiscountPackager($builder))->getMatchingItems(
            new DiscountLineItem('label', new AbsolutePriceDefinition(10), $payload, null),
            new Cart('token'),
            static::createStub(SalesChannelContext::class),
        );
    }
}
