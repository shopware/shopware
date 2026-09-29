<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Derives the read-only `descriptionTeaser` from the `description` on write via the shared
 * {@see ProductDescriptionTeaserBuilder}, keeping the teaser cheap to load in listings without
 * stripping HTML on every read. Existing products are backfilled asynchronously by the
 * `product.description_teaser.indexer` scheduled in the migration that adds the column.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDescriptionTeaserSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ProductDescriptionTeaserBuilder $teaserBuilder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
        ];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        $commands = $event->getCommandsForEntity(ProductTranslationDefinition::ENTITY_NAME);

        foreach ($commands as $command) {
            if (!$command->hasField('description')) {
                continue;
            }

            $command->addPayload('description_teaser', $this->teaserBuilder->build($command->getPayload()['description'] ?? null));
        }
    }
}
