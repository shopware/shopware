<?php declare(strict_types=1);

namespace unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupScopeDiscountPackager;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(SetGroupScopeDiscountPackager::class)]
#[Package('checkout')]
class SetGroupScopeDiscountPackagerTest extends TestCase
{
    public function testFormatRuleCollection(): void
    {
        $builder = $this->createMock(LineItemGroupBuilder::class);
        $builder
            ->method('findGroupPackages')
            ->willReturnCallback(function (array $groupDefinitions) {
                static::assertCount(3, $groupDefinitions);

                $array = $groupDefinitions[0]->getRules();
                $collection = $groupDefinitions[1]->getRules();
                $null = $groupDefinitions[2]->getRules();

                static::assertInstanceOf(RuleCollection::class, $array);
                static::assertCount(1, $array);
                static::assertSame('Rule Name', $array[0]->getName());

                static::assertInstanceOf(RuleCollection::class, $collection);
                static::assertCount(0, $collection);

                static::assertInstanceOf(RuleCollection::class, $null);
                static::assertCount(0, $null);

                return new LineItemGroupBuilderResult();
            });

        $payload = [
            'discountScope' => 'scope',
            'discountType' => 'type',
            'setGroups' => [
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                    'rules' => [['name' => 'Rule Name']]
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                    'rules' => new RuleCollection()
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC'
                ]
            ],
        ];

        (new SetGroupScopeDiscountPackager($builder))->getMatchingItems(
            new DiscountLineItem('label', new AbsolutePriceDefinition(10), $payload, null),
            new Cart('token'),
            $this->createMock(SalesChannelContext::class)
        );
    }
}
