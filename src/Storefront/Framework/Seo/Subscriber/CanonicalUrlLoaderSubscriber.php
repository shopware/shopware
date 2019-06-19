<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Subscriber;

use function Flag\next741;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Framework\Seo\Entity\Field\CanonicalUrlField;
use Shopware\Storefront\Framework\Seo\SeoUrl\CanonicalUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CanonicalUrlLoaderSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;
    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    public function __construct(EntityRepositoryInterface $seoUrlRepository, SeoUrlRouteRegistry $seoUrlRouteRegistry)
    {
        $this->seoUrlRepository = $seoUrlRepository;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        if (!next741()) {
            return [];
        }

        // TODO: register depending on the registered SeoUrlRoutes
        return [
            'product.loaded' => ['addCanonicals', 10],
            'category.loaded' => ['addCanonicals', 10],
        ];
    }

    /**
     * @internal
     */
    public function addCanonicals(EntityLoadedEvent $event): void
    {
        $source = $event->getContext()->getSource();
        // SalesChannelApiSource only
        if (!$source instanceof SalesChannelApiSource) {
            return;
        }

        $canonicalUrlFields = $this->getCanonicalUrlFields($event);
        /** @var CanonicalUrlField $canonicalUrlField */
        foreach ($canonicalUrlFields as $canonicalUrlField) {
            $this->addCanonicalsForField($canonicalUrlField, $source->getSalesChannelId(), $event);
        }
    }

    private function addCanonicalsForField(CanonicalUrlField $canonicalUrlField, string $salesChannelId, EntityLoadedEvent $event): void
    {
        $routeName = $canonicalUrlField->getRouteName();
        $fks = $this->extractForeignKeys($routeName, $event);
        if (empty($fks)) {
            return;
        }
        $canonicalUrls = $this->getCanonicalUrls(
            $event->getContext(),
            $routeName,
            $salesChannelId,
            $fks
        );

        $propName = $canonicalUrlField->getPropertyName();
        /** @var Entity $entity */
        foreach ($event->getEntities() as $entity) {
            $id = $entity->getUniqueIdentifier();
            if ($canonicalUrls->has($id)) {
                $entity->addExtension($propName, $canonicalUrls->get($id));
            }
        }
    }

    private function getCanonicalUrls(Context $context, string $routeName, string $salesChannelId, array $foreignKeys): CanonicalUrlCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('routeName', $routeName),
            new EqualsFilter('languageId', $context->getLanguageId()),
            new EqualsAnyFilter('foreignKey', $foreignKeys),
            new EqualsFilter('isValid', true),
            new EqualsFilter('isCanonical', true)
        );
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addSorting(
            new FieldSorting('foreignKey'),
            new FieldSorting('salesChannelId', FieldSorting::ASCENDING)
        );

        /** @var SeoUrlCollection $canonicalUrls */
        $canonicalUrls = $this->seoUrlRepository->search($criteria, $context)->getEntities();

        return new CanonicalUrlCollection($canonicalUrls);
    }

    private function extractForeignKeys(string $routeName, EntityLoadedEvent $event): array
    {
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($routeName);
        if (!$seoUrlRoute) {
            return [];
        }
        $config = $seoUrlRoute->getConfig();

        if (!$event->getDefinition()->isInstanceOf($config->getDefinition())) {
            return [];
        }

        return $event->getIds();
    }

    private function getCanonicalUrlFields(EntityLoadedEvent $event): FieldCollection
    {
        return $event->getDefinition()->getFields()->filterInstance(CanonicalUrlField::class);
    }
}
