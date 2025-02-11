<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CmsSlotsDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->initTestSubscriber();
        $this->initData();
    }

    protected function tearDown(): void
    {
        $this->removeTestSubscriber();
    }

    public function testProductSliderAcceptsCustomAssociations(): void
    {
        $resolverContext = new ResolverContext($this->context, new Request());

        $config = new FieldConfig('products', 'product_stream', $this->ids->get('stream'));
        $configs = new FieldConfigCollection();
        $configs->add($config);

        $slot = new CmsSlotEntity();
        $slot->setId(Uuid::randomHex());
        $slot->setType('product-slider');
        $slot->setSlot('productSlider');
        $slot->setFieldConfig($configs);
        $slot->setBlockId(Uuid::randomHex());

        $slots = new CmsSlotCollection();
        $slots->add($slot);

        $resolver = $this->getContainer()->get(CmsSlotsDataResolver::class);
        $result = $resolver->resolve($slots, $resolverContext);

        $productSliderData = $result->first()?->getData() ?? null;
        static::assertInstanceOf(ProductSliderStruct::class, $productSliderData);

        $product = $productSliderData->getProducts()?->get($this->ids->get('product-1'));
        static::assertInstanceOf(ProductEntity::class, $product);

        $tags = $product->getTags();
        static::assertInstanceOf(TagCollection::class, $tags);
        static::assertCount(1, $tags);
    }

    private function initTestSubscriber(): void
    {
        $testSubscriber = new CmsSlotsDataTestSubscriber();

        $this->getContainer()->set(CmsSlotsDataTestSubscriber::class, $testSubscriber);
        $this->getContainer()->get('event_dispatcher')->addSubscriber($testSubscriber);
    }

    private function initData(): void
    {
        $context = Context::createDefaultContext();
        $this->getContainer()->get('product_stream.repository')->create([
            [
                'id' => $this->ids->get('stream'),
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $context);

        $taxIds = $this->context->getTaxRules()->getIds();
        $this->ids->set('t1', (string) array_pop($taxIds));

        $products = [
            (new ProductBuilder($this->ids, 'product-1'))
                ->price(100)
                ->visibility()
                ->tag('tag-1')
                ->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, $context);
    }

    private function removeTestSubscriber(): void
    {
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $testSubscriber = $this->getContainer()->get(CmsSlotsDataTestSubscriber::class);
        \assert($testSubscriber instanceof CmsSlotsDataTestSubscriber);

        $eventDispatcher->removeSubscriber($testSubscriber);
    }
}

/**
 * @internal
 */
class CmsSlotsDataTestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CmsSlotsDataCollectExtension::NAME . '.post' => 'addAssociations',
        ];
    }

    public function addAssociations(CmsSlotsDataCollectExtension $extension): void
    {
        $collection = current($extension->result);
        \assert($collection instanceof CriteriaCollection);

        $list = $collection->all()[ProductDefinition::class] ?? null;
        \assert(\is_array($list));

        $criteria = current($list);
        \assert($criteria instanceof Criteria);

        $criteria->addAssociation('tags');
    }
}
