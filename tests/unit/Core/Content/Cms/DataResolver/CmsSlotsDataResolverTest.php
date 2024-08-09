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
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsDataResolverTest extends TestCase
{
    public function testResolveCallsCollectedResolvers(): void
    {
        $resolver = $this->getCmsSlotsDataResolver();

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

        $context = Generator::createSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        // assertion in mocked resolver method calls
        $resolver->resolve($slots, $resolverContext);
    }

    private function getCmsSlotsDataResolver(): CmsSlotsDataResolver
    {
        $criteria = new Criteria(['id-1', 'id-2']);
        $criteria->addFilter(new EqualsFilter('config', null));

        $criteria2 = new Criteria(['id-3', 'id-4']);

        $collection = new CriteriaCollection();
        $collection->add('criteria-1', 'slot', $criteria);
        $collection->add('criteria-2', 'slot', $criteria2);

        $formResolver = $this->createMock(FormCmsElementResolver::class);
        $formResolver->method('getType')->willReturn('form');
        $formResolver->expects(static::once())->method('enrich');
        $formResolver->method('collect')->willReturn($collection);

        $htmlResolver = $this->createMock(HtmlCmsElementResolver::class);
        $htmlResolver->method('getType')->willReturn('html');
        $htmlResolver->expects(static::once())->method('enrich');

        $textResolver = $this->createMock(TextCmsElementResolver::class);
        $textResolver->method('getType')->willReturn('text');
        $textResolver->expects(static::never())->method('enrich');

        /** @var SalesChannelRepository<SalesChannelProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->method('search')->willReturn($this->createMock(EntitySearchResult::class));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $productDefinition = new ProductDefinition();
        $productDefinition->compile($registry);

        $registry->method('get')->willReturn($productDefinition);

        return new CmsSlotsDataResolver(
            [$formResolver, $htmlResolver, $textResolver],
            ['product' => $productRepository],
            $registry
        );
    }
}
