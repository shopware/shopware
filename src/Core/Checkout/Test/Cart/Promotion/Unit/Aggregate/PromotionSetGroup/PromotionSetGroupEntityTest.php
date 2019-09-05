<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Aggregate\PromotionSetGroup;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionSetGroupEntityTest extends TestCase
{
    private const KEY_PACKAGER_COUNT = 'PACKAGER_COUNT';

    private const KEY_SORTER_PRICE_ASC = 'PRICE_ASC';
    private const KEY_SORTER_PRICE_DESC = 'PRICE_DESC';

    /**
     * @var SalesChannelContext
     */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * This test verifies that our assignment and
     * getter work correctly for the property.
     *
     * @test
     * @group promotions
     */
    public function testPropertyPackagerKey(): void
    {
        $group = new PromotionSetGroupEntity();
        $group->setPackagerKey(self::KEY_PACKAGER_COUNT);
        $group->setValue(9);
        $group->setSorterKey('');
        $group->setSetGroupRules(new RuleCollection());

        static::assertEquals(self::KEY_PACKAGER_COUNT, $group->getPackagerKey());
    }

    /**
     * This test verifies that our assignment and
     * getter work correctly for the property.
     *
     * @test
     * @group promotions
     */
    public function testPropertyValue(): void
    {
        $group = new PromotionSetGroupEntity();
        $group->setPackagerKey('0');
        $group->setValue(1);
        $group->setSorterKey('');
        $group->setSetGroupRules(new RuleCollection());

        static::assertEquals(1, $group->getValue());
    }

    /**
     * This test verifies that our assignment and
     * getter work correctly for the property.
     *
     * @test
     * @group promotions
     */
    public function testPropertySorterKey(): void
    {
        $group = new PromotionSetGroupEntity();
        $group->setPackagerKey('0');
        $group->setValue(9);
        $group->setSorterKey('PRICE_DESC');
        $group->setSetGroupRules(new RuleCollection());

        static::assertEquals('PRICE_DESC', $group->getSorterKey());
    }

    /**
     * This test verifies that our assignment and
     * getter work correctly for the property.
     *
     * @test
     * @group promotions
     */
    public function testPropertyRules(): void
    {
        $ruleEntity = new RuleEntity();
        $ruleEntity->setId('R1');

        $group = new PromotionSetGroupEntity();
        $group->setPackagerKey('0');
        $group->setValue(9);
        $group->setSorterKey('');
        $group->setSetGroupRules(new RuleCollection([$ruleEntity]));

        static::assertEquals(1, $group->getSetGroupRules()->count());
    }
}
