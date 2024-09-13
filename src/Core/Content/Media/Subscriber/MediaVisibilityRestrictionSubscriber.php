<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
class MediaVisibilityRestrictionSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'securePrivateFolders',
        ];
    }

    public function securePrivateFolders(EntitySearchedEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        if ($event->getDefinition()->getEntityName() === MediaFolderDefinition::ENTITY_NAME) {
            $event->getCriteria()->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('media_folder.configuration.private', false),
                    new EqualsFilter('media_folder.configuration.private', null),
                ])
            );

            return;
        }

        if ($event->getDefinition()->getEntityName() === MediaDefinition::ENTITY_NAME) {
            $event->getCriteria()->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('private', false),
                    new MultiFilter('AND', [
                        new EqualsFilter('private', true),
                        new EqualsFilter('mediaFolder.defaultFolder.entity', 'product_download'),
                    ]),
                ])
            );
        }
    }
}
