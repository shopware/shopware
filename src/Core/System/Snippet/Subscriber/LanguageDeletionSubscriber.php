<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Drops the community translation metadata of a language once it is deleted. Otherwise, the locale would stay in
 * crowdin-metadata.lock and the "update all" action would treat it as installed, re-downloading and re-creating
 * the removed language. Listening on the DAL delete event covers both the Admin API and CLI or programmatic deletes.
 *
 * @internal
 */
#[Package('discovery')]
class LanguageDeletionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslationMetadataStore $metadataStore,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        /** @var list<string> $ids */
        $ids = $event->getIds(LanguageDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        // Resolve the locale codes while the languages still exist; act only after the delete succeeded.
        $localeCodes = $this->fetchLocaleCodes($ids);

        if ($localeCodes === []) {
            return;
        }

        $event->addSuccess(function () use ($localeCodes): void {
            foreach ($localeCodes as $localeCode) {
                $this->metadataStore->remove($localeCode);
            }
        });
    }

    /**
     * @param list<string> $languageIds
     *
     * @return list<string>
     */
    private function fetchLocaleCodes(array $languageIds): array
    {
        $codes = $this->connection->fetchFirstColumn(
            <<<'SQL'
            SELECT `locale`.`code`
            FROM `language`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `language`.`id` IN (:ids)
            SQL,
            ['ids' => Uuid::fromHexToBytesList($languageIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_values(array_filter($codes));
    }
}
