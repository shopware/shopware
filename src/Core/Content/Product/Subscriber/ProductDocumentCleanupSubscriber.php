<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A product document can't exist without its version in the default language.
 * When that version is deleted, for example together with its file, the whole document is deleted.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDocumentCleanupSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     */
    public function __construct(private readonly EntityRepository $productDocumentRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'deleteDocumentsWithoutDefaultTranslation',
        ];
    }

    public function deleteDocumentsWithoutDefaultTranslation(EntityDeleteEvent $event): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $documentsWithoutDefault = [];
        $deletedDocuments = [];

        foreach ($event->getCommands() as $command) {
            $primaryKey = $command->getPrimaryKey();

            if ($command->getEntityName() === ProductDocumentDefinition::ENTITY_NAME) {
                $deletedDocuments[$primaryKey['id'] . $primaryKey['version_id']] = true;

                continue;
            }

            if ($command->getEntityName() === ProductDocumentTranslationDefinition::ENTITY_NAME && $primaryKey['language_id'] === $systemLanguageId) {
                $documentsWithoutDefault[$primaryKey['product_document_id'] . $primaryKey['product_document_version_id']] = [
                    'id' => Uuid::fromBytesToHex($primaryKey['product_document_id']),
                    'versionId' => Uuid::fromBytesToHex($primaryKey['product_document_version_id']),
                ];
            }
        }

        $documentsWithoutDefault = array_values(array_diff_key($documentsWithoutDefault, $deletedDocuments));
        if ($documentsWithoutDefault === []) {
            return;
        }

        $event->addSuccess(function () use ($documentsWithoutDefault, $event): void {
            $event->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($documentsWithoutDefault): void {
                $this->productDocumentRepository->delete($documentsWithoutDefault, $context);
            });
        });
    }
}
