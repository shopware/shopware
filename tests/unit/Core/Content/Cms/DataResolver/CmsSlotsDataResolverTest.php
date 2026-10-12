<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataEnrichExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataResolveExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsDataResolverTest extends TestCase
{
    private FormCmsElementResolver&MockObject $formResolver;

    private HtmlCmsElementResolver&MockObject $htmlResolver;

    private TextCmsElementResolver&MockObject $textResolver;

    private DefinitionInstanceRegistry&Stub $registry;

    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>&Stub
     */
    private SalesChannelRepository&Stub $productRepository;

    private EventDispatcher&Stub $dispatcher;

    private ExtensionDispatcher $extensions;

    protected function setUp(): void
    {
        $this->formResolver = $this->createMock(FormCmsElementResolver::class);
        $this->htmlResolver = $this->createMock(HtmlCmsElementResolver::class);
        $this->textResolver = $this->createMock(TextCmsElementResolver::class);
        $this->registry = static::createStub(DefinitionInstanceRegistry::class);
        $this->productRepository = static::createStub(SalesChannelRepository::class);
        $this->dispatcher = static::createStub(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($this->dispatcher);
    }

    public function testResolveCallsCollectedResolvers(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-2',
                'slot' => 'content',
                'type' => 'html',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-3',
                'slot' => 'right',
                'type' => 'invalid',
            ]),
        ]);

        $criteria = new Criteria(['id-1', 'id-2']);
        $criteria->addFilter(new EqualsFilter('config', null));

        $criteria2 = new Criteria(['id-3', 'id-4']);

        $collection = new CriteriaCollection();
        $collection->add('criteria-1', 'slot', $criteria);
        $collection->add('criteria-2', 'slot', $criteria2);

        $this->formResolver->method('collect')->willReturn($collection);

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->expects($this->once())->method('enrich');

        $this->htmlResolver->method('getType')->willReturn('html');
        $this->htmlResolver->expects($this->once())->method('enrich');

        $this->textResolver->method('getType')->willReturn('text');
        $this->textResolver->expects($this->never())->method('enrich');

        $context = Generator::generateSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $resolver = $this->getCmsSlotsDataResolver();

        // assertion in mocked resolver method calls
        $resolver->resolve($slots, $resolverContext);
    }

    public function testResolvePublishesExtensions(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
        ]);

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->expects($this->once())->method('enrich');
        $this->htmlResolver->expects($this->never())->method('enrich');
        $this->textResolver->expects($this->never())->method('enrich');

        $criteria = new Criteria(['id-1', 'id-2']);
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('criteria-1', 'slot', $criteria);

        $this->formResolver->method('collect')->willReturn($criteriaCollection);

        $context = Generator::generateSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $dispatcher = $this->createMock(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($dispatcher);
        $dispatcher
            // 3 extensions, each dispatched as pre- and post-event
            ->expects($this->exactly(6))
            ->method('dispatch')
            ->willReturnCallback(static function (Extension $extension) use ($slots, $resolverContext, $criteriaCollection) {
                switch (true) {
                    case $extension instanceof CmsSlotsDataResolveExtension:
                        static::assertSame($slots, $extension->slots);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertInstanceOf(CmsSlotCollection::class, $extension->result);
                            static::assertCount(1, $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataCollectExtension:
                        static::assertCount(1, $extension->slots);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertSame(['slot-1' => $criteriaCollection], $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataEnrichExtension:
                        static::assertSame($slots, $extension->slots);
                        static::assertSame(['slot-1' => $criteriaCollection], $extension->criteriaList);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertInstanceOf(CmsSlotCollection::class, $extension->result);
                            static::assertCount(1, $extension->result);
                        }

                        return $extension;
                    default:
                        static::fail('No expected event was dispatched');
                }
            });

        $this->getCmsSlotsDataResolver()->resolve($slots, $resolverContext);
    }

    public function testResolveFiltersMergedDirectReadsPerSlot(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
        ]);

        $collection = new CriteriaCollection();
        $collection->add('criteria-1', ProductDefinition::class, new Criteria(['id-1']));

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->method('collect')->willReturn($collection);
        $this->htmlResolver->expects($this->never())->method('enrich');
        $this->textResolver->expects($this->never())->method('enrich');

        $context = Generator::generateSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $wanted = (new SalesChannelProductEntity())->assign(['id' => 'id-1']);
        $other = (new SalesChannelProductEntity())->assign(['id' => 'id-2']);
        $wanted->internalSetEntityData('product', new FieldVisibility([]));
        $other->internalSetEntityData('product', new FieldVisibility([]));

        // the merged direct read fetches the ids of all slots at once, each slot only gets its own ids back
        $this->productRepository->method('search')->willReturn(new EntitySearchResult(
            'product',
            2,
            new SalesChannelProductCollection([$wanted, $other]),
            null,
            new Criteria(['id-1', 'id-2']),
            $context->getContext(),
        ));

        $this->formResolver->expects($this->once())->method('enrich')
            ->willReturnCallback(static function (CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $data) use ($wanted): void {
                $filtered = $data->get('criteria-1');

                static::assertInstanceOf(EntitySearchResult::class, $filtered);
                static::assertSame(1, $filtered->getTotal());
                static::assertInstanceOf(SalesChannelProductCollection::class, $filtered->getEntities());
                static::assertSame(['id-1' => $wanted], $filtered->getEntities()->getElements());
            });

        $productDefinition = new ProductDefinition();
        $productDefinition->compile($this->registry);
        $this->registry->method('get')->willReturn($productDefinition);

        $resolver = new CmsSlotsDataResolver(
            [$this->formResolver, $this->htmlResolver, $this->textResolver],
            ['product' => $this->productRepository],
            $this->registry,
            $this->extensions,
        );

        $resolver->resolve($slots, $resolverContext);
    }

    private function getCmsSlotsDataResolver(): CmsSlotsDataResolver
    {
        $this->productRepository->method('search')
            ->willReturn(static::createStub(EntitySearchResult::class));

        $productDefinition = new ProductDefinition();
        $productDefinition->compile($this->registry);

        $this->registry->method('get')->willReturn($productDefinition);

        return new CmsSlotsDataResolver(
            [$this->formResolver, $this->htmlResolver, $this->textResolver],
            ['product' => $this->productRepository],
            $this->registry,
            $this->extensions,
        );
    }
}
