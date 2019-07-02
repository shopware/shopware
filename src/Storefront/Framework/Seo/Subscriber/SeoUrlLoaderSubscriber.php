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
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Seo\Entity\Field\CanonicalUrlField;
use Shopware\Storefront\Framework\Seo\SeoUrl\CanonicalUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SeoUrlLoaderSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(EntityRepositoryInterface $seoUrlRepository, SeoUrlRouteRegistry $seoUrlRouteRegistry, RequestStack $requestStack)
    {
        $this->seoUrlRepository = $seoUrlRepository;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
        $this->requestStack = $requestStack;
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
            'seo_url.loaded' => ['addUrls', 10],
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

    /**
     * @internal
     */
    public function addUrls(EntityLoadedEvent $event): void
    {
        /** @var SeoUrlEntity $seoUrl */
        foreach ($event->getEntities() as $seoUrl) {
            $this->setUrl($seoUrl);
        }
    }

    private function setUrl(SeoUrlEntity $seoUrlEntity): void
    {
        $request = $this->requestStack->getMasterRequest();
        if (!$request) {
            return;
        }

        $scBasePath = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL);
        $seoUrlEntity->setUrl($scBasePath . '/' . trim($seoUrlEntity->getSeoPathInfo(), '/'));
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
                $canonicalUrl = $canonicalUrls->get($id);
                $this->setUrl($canonicalUrl);
                $entity->addExtension($propName, $canonicalUrl);
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
