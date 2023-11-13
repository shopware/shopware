<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FormTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testType(): void
    {
        $formCmsElementResolver = $this->getContainer()->get(FormCmsElementResolver::class);

        static::assertEquals('form', $formCmsElementResolver->getType());
    }

    public function testResolverUsesAbstractSalutationsRouteToEnrichSlot(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection));

        $formElement = $this->getCmsFormElement();

        $formCmsElementResolver->enrich(
            $formElement,
            new ResolverContext($this->createMock(SalesChannelContext::class), new Request()),
            new ElementDataCollection()
        );

        static::assertSame($formElement->getData(), $salutationCollection);
    }

    public function testResolverSortsSalutationsBySalutationKeyDesc(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection));

        $formElement = $this->getCmsFormElement();

        $formCmsElementResolver->enrich(
            $formElement,
            new ResolverContext($this->createMock(SalesChannelContext::class), new Request()),
            new ElementDataCollection()
        );

        /** @var SalutationCollection $enrichedCollection */
        $enrichedCollection = $formElement->getData();

        $sortedKeys = array_values($enrichedCollection->map(static fn (SalutationEntity $salutation) => $salutation->getSalutationKey()));

        static::assertEquals(['d', 'c', 'b', 'a'], $sortedKeys);
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
