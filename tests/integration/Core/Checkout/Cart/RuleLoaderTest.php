<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\RuleLoader;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RuleLoader::class)]
class RuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    private AbstractRuleLoader $ruleLoader;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->ruleLoader = $this->getContainer()->get(RuleLoader::class);
        $this->ids = new IdsCollection();

        $this->createRules();
    }

    public function testLoadBasicRuleIds(): void
    {
        $ids = $this->ruleLoader->loadIds(Context::createDefaultContext());

        static::assertContains($this->ids->get('valid'), $ids);
        static::assertContains($this->ids->get('flow'), $ids);
        static::assertContains($this->ids->get('flow-condition'), $ids);
        static::assertContains($this->ids->get('flow-and-condition'), $ids);
        static::assertContains($this->ids->get('empty'), $ids);
        static::assertContains($this->ids->get('sales-channel-filter'), $ids);
        static::assertContains($this->ids->get('random-sales-channel-filter'), $ids);
        static::assertContains($this->ids->get('area-null'), $ids);

        static::assertNotContains($this->ids->get('invalid'), $ids);
    }

    public function testLoadContextRuleIds(): void
    {
        $contextIds = $this->ruleLoader->loadIds(Context::createDefaultContext(), RuleLoader::TYPE_CONTEXT);

        static::assertContains($this->ids->get('valid'), $contextIds);
        static::assertContains($this->ids->get('flow'), $contextIds);
        static::assertContains($this->ids->get('empty'), $contextIds);
        static::assertContains($this->ids->get('sales-channel-filter'), $contextIds);
        static::assertContains($this->ids->get('random-sales-channel-filter'), $contextIds);

        static::assertNotContains($this->ids->get('flow-condition'), $contextIds);
        static::assertNotContains($this->ids->get('flow-and-condition'), $contextIds);
        static::assertNotContains($this->ids->get('invalid'), $contextIds);
        static::assertNotContains($this->ids->get('area-null'), $contextIds);
    }

    public function testFlowRuleIds(): void
    {
        $flowIds = $this->ruleLoader->loadIds(Context::createDefaultContext(), RuleLoader::TYPE_FLOW);

        static::assertContains($this->ids->get('flow'), $flowIds);
        static::assertContains($this->ids->get('flow-and-condition'), $flowIds);

        static::assertNotContains($this->ids->get('valid'), $flowIds);
        static::assertNotContains($this->ids->get('flow-condition'), $flowIds);
        static::assertNotContains($this->ids->get('empty'), $flowIds);
        static::assertNotContains($this->ids->get('invalid'), $flowIds);
        static::assertNotContains($this->ids->get('sales-channel-filter'), $flowIds);
        static::assertNotContains($this->ids->get('random-sales-channel-filter'), $flowIds);
        static::assertNotContains($this->ids->get('area-null'), $flowIds);
    }

    public function testSalesChannelFilterRuleIds(): void
    {
        $salesChannelIds = $this->ruleLoader->loadIds(Context::createDefaultContext(new SalesChannelApiSource($this->ids->get('sales-channel'))));

        static::assertContains($this->ids->get('valid'), $salesChannelIds);
        static::assertContains($this->ids->get('flow'), $salesChannelIds);
        static::assertContains($this->ids->get('flow-condition'), $salesChannelIds);
        static::assertContains($this->ids->get('flow-and-condition'), $salesChannelIds);
        static::assertContains($this->ids->get('empty'), $salesChannelIds);
        static::assertContains($this->ids->get('sales-channel-filter'), $salesChannelIds);
        static::assertContains($this->ids->get('area-null'), $salesChannelIds);

        static::assertNotContains($this->ids->get('invalid'), $salesChannelIds);
        static::assertNotContains($this->ids->get('random-sales-channel-filter'), $salesChannelIds);
    }

    public function testSalesChannelFilterRuleIdsWrongId(): void
    {
        $randomUuid = $this->ruleLoader->loadIds(Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex())));

        static::assertContains($this->ids->get('flow'), $randomUuid);
        static::assertContains($this->ids->get('valid'), $randomUuid);
        static::assertContains($this->ids->get('flow-condition'), $randomUuid);
        static::assertContains($this->ids->get('flow-and-condition'), $randomUuid);
        static::assertContains($this->ids->get('empty'), $randomUuid);
        static::assertContains($this->ids->get('area-null'), $randomUuid);

        static::assertNotContains($this->ids->get('invalid'), $randomUuid);
        static::assertNotContains($this->ids->get('sales-channel-filter'), $randomUuid);
        static::assertNotContains($this->ids->get('random-sales-channel-filter'), $randomUuid);
    }

    private function createRules(): void
    {
        $customDomain = [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.random',
            ],
        ];

        $this->createSalesChannel(['id' => $this->ids->create('sales-channel')]);
        $this->createSalesChannel(['id' => $this->ids->create('random-sales-channel'), 'domains' => $customDomain]);

        $rules = [
            [
                'id' => $this->ids->create('valid'),
                'name' => 'Always valid',
                'priority' => 1,
                'invalid' => false,
                'ruleConditions' => [
                    [
                        'type' => 'alwaysValid',
                    ],
                ],
                'areas' => [RuleAreas::PRODUCT_AREA],
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('invalid'),
                'name' => 'Invalid',
                'priority' => 2,
                'invalid' => true,
                'areas' => [],
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('flow'),
                'name' => 'Flow area',
                'priority' => 2,
                'invalid' => false,
                'areas' => [RuleAreas::FLOW_AREA],
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('flow-condition'),
                'name' => 'Flow condition',
                'priority' => 2,
                'invalid' => false,
                'areas' => [RuleAreas::FLOW_CONDITION_AREA],
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('empty'),
                'name' => 'Empty area',
                'priority' => 2,
                'invalid' => false,
                'areas' => [],
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('sales-channel-filter'),
                'name' => 'Sales channel filter',
                'priority' => 2,
                'invalid' => false,
                'areas' => [RuleAreas::PRODUCT_AREA],
                'filterBySalesChannel' => true,
                'salesChannels' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
            ],
            [
                'id' => $this->ids->create('random-sales-channel-filter'),
                'name' => 'Random sales channel filter',
                'priority' => 2,
                'invalid' => false,
                'areas' => [RuleAreas::PRODUCT_AREA],
                'filterBySalesChannel' => true,
                'salesChannels' => [
                    ['id' => $this->ids->get('random-sales-channel')],
                ],
            ],
            [
                'id' => $this->ids->create('area-null'),
                'name' => 'Area null',
                'priority' => 2,
                'invalid' => false,
                'areas' => null,
                'filterBySalesChannel' => false,
            ],
            [
                'id' => $this->ids->create('flow-and-condition'),
                'name' => 'Flow and condition',
                'priority' => 2,
                'invalid' => false,
                'areas' => [RuleAreas::FLOW_AREA, RuleAreas::FLOW_CONDITION_AREA, RuleAreas::PRODUCT_AREA],
                'filterBySalesChannel' => false,
            ],
        ];

        // Disable Indexing, prevent area to be overwritten
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->ruleRepository->upsert($rules, $context));
    }
}
