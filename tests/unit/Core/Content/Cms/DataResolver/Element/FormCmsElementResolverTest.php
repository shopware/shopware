<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FormCmsElementResolver::class)]
class FormCmsElementResolverTest extends TestCase
{
    public function testType(): void
    {
        $formCmsElementResolver = new FormCmsElementResolver($this->createMock(AbstractSalutationRoute::class));

        static::assertSame('form', $formCmsElementResolver->getType());
    }

    public function testResolverUsesAbstractSalutationsRouteToEnrichSlot(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection));

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($formElement->getData(), $salutationCollection);
    }

    public function testResolverSortsSalutationsBySalutationKeyDesc(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection));

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        $enrichedCollection = $formElement->getData();
        static::assertInstanceOf(SalutationCollection::class, $enrichedCollection);

        $sortedKeys = array_values($enrichedCollection->map(static fn (SalutationEntity $salutation) => $salutation->getSalutationKey()));

        static::assertSame(['d', 'c', 'b', 'a'], $sortedKeys);
    }

    public function testCollectReturnsNull(): void
    {
        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());
        $salutationRoute = $this->createMock(AbstractSalutationRoute::class);

        $formCmsElementResolver = new FormCmsElementResolver($salutationRoute);
        $actual = $formCmsElementResolver->collect(new CmsSlotEntity(), $context);

        static::assertNull($actual);
    }

    private function getCmsFormElement(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setType('form');
        $slot->setUniqueIdentifier('id');

        return $slot;
    }

    private function getSalutationCollection(): SalutationCollection
    {
        return new SalutationCollection([
            $this->createSalutationWithSalutationKey('c'),
            $this->createSalutationWithSalutationKey('a'),
            $this->createSalutationWithSalutationKey('d'),
            $this->createSalutationWithSalutationKey('b'),
        ]);
    }

    private function createSalutationWithSalutationKey(string $salutationKey): SalutationEntity
    {
        return (new SalutationEntity())->assign([
            'id' => Uuid::randomHex(),
            'salutationKey' => $salutationKey,
        ]);
    }

    private function getSalutationRoute(SalutationCollection $salutationCollection): AbstractSalutationRoute
    {
        $salutationRoute = $this->createMock(AbstractSalutationRoute::class);
        $salutationRoute->expects(static::once())
            ->method('load')
            ->willReturn(new SalutationRouteResponse(
                new EntitySearchResult(
                    SalutationDefinition::ENTITY_NAME,
                    $salutationCollection->count(),
                    $salutationCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        return $salutationRoute;
    }
}
