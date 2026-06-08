<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Extension\SalesChannelCmsPageLoaderExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the CMS page(s) yourself — e.g. a customer-group specific layout or an
 * external source — instead of the core loader.
 */
readonly class SalesChannelCmsPageLoaderExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelCmsPageLoaderExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(SalesChannelCmsPageLoaderExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->request, $event->criteria, $event->context, $event->config

        $page = (new CmsPageEntity())->assign(['id' => 'example-page']);

        $event->result = new EntitySearchResult(
            CmsPageDefinition::ENTITY_NAME,
            1,
            new CmsPageCollection([$page]),
            null,
            $event->criteria,
            $event->context->getContext(),
        );

        // stop propagation so the core CMS page loading is skipped
        $event->stopPropagation();
    }
}
