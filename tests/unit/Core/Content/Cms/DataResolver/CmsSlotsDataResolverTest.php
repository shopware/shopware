<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataEnrichExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataResolveExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
#[Package('buyers-experience')]
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsDataResolverTest extends TestCase
{
    private FormCmsElementResolver&MockObject $formResolver;

    private HtmlCmsElementResolver&MockObject $htmlResolver;

    private TextCmsElementResolver&MockObject $textResolver;

    private DefinitionInstanceRegistry&MockObject $registry;

    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>&MockObject
     */
    private SalesChannelRepository&MockObject $productRepository;

    private EventDispatcher&MockObject $dispatcher;

    private ExtensionDispatcher $extensions;

    protected function setUp(): void
    {
        $this->formResolver = $this->createMock(FormCmsElementResolver::class);
        $this->htmlResolver = $this->createMock(HtmlCmsElementResolver::class);
        $this->textResolver = $this->createMock(TextCmsElementResolver::class);
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcher::class);
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
        $this->formResolver->expects(static::once())->method('enrich');

        $this->htmlResolver->method('getType')->willReturn('html');
        $this->htmlResolver->expects(static::once())->method('enrich');

        $this->textResolver->method('getType')->willReturn('text');
        $this->textResolver->expects(static::never())->method('enrich');

        $context = Generator::createSalesChannelContext();
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
        $this->formResolver->expects(static::once())->method('enrich');

        $criteria = new Criteria(['id-1', 'id-2']);
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('criteria-1', 'slot', $criteria);

        $this->formResolver->method('collect')->willReturn($criteriaCollection);

        $context = Generator::createSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $this->dispatcher
            // 3 extensions, each dispatched as pre- and post-event
            ->expects(static::exactly(6))
            ->method('dispatch')
            ->willReturnCallback(function (Extension $extension) use ($slots, $resolverContext, $criteriaCollection) {
                switch (true) {
                    case $extension instanceof CmsSlotsDataResolveExtension:
                        static::assertEquals($slots, $extension->slots);
                        static::assertEquals($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertInstanceOf(CmsSlotCollection::class, $extension->result);
                            static::assertCount(1, $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataCollectExtension:
                        static::assertCount(1, $extension->slots);
                        static::assertEquals($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertEquals(['slot-1' => $criteriaCollection], $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataEnrichExtension:
                        static::assertEquals($slots, $extension->slots);
                        static::assertEquals(['slot-1' => $criteriaCollection], $extension->criteriaList);
                        static::assertEquals($resolverContext, $extension->resolverContext);

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

    private function getCmsSlotsDataResolver(): CmsSlotsDataResolver
    {
        $this->productRepository->method('search')
            ->willReturn($this->createMock(EntitySearchResult::class));

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
