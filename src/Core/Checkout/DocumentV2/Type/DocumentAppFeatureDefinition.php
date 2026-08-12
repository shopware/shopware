<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\AppFeaturePersistListener;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;

/**
 * Maps the manifest `<documents>` block to `app_feature` rows of type `document`.
 *
 * @internal
 *
 * @implements AppFeatureDefinition<AppDocumentTypeConfig>
 *
 * @phpstan-type DocumentPayload array{identifier: string, formats: list<string>, label: array<string, string>, config: array<string, scalar>}
 */
#[Package('after-sales')]
final class DocumentAppFeatureDefinition implements AppFeatureDefinition, AppFeaturePersistListener
{
    final public const TYPE = 'document';

    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @param EntityRepository<NumberRangeTypeCollection> $numberRangeTypeRepository
     * @param EntityRepository<NumberRangeCollection> $numberRangeRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $numberRangeTypeRepository,
        private readonly EntityRepository $numberRangeRepository,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return AppDocumentTypeConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $documents = $manifest->getDocuments();

        if ($documents === null) {
            return [];
        }

        return array_map(
            static fn (array $documentType): AppDocumentTypeConfig => new AppDocumentTypeConfig(
                $documentType['identifier'],
                $documentType['formats'],
                self::resolveLabel($documentType['label'], $defaultLocale),
                $documentType['config'],
            ),
            $documents->getDocumentTypes(),
        );
    }

    public function onPersist(AppPersistContext $context): void
    {
        $documents = $context->manifest->getDocuments();

        if ($documents === null) {
            return;
        }

        $documentTypes = $documents->getDocumentTypes();

        $this->assertNoCollision($documentTypes, $context->app->getName());
        $this->seedNumberRanges($documentTypes, $context->context);
    }

    /**
     * @return DocumentPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'identifier' => $declared->getName(),
            'formats' => $declared->getFormats(),
            'label' => $declared->getLabel(),
            'config' => $declared->getConfig(),
        ];
    }

    /**
     * @param DocumentPayload $payload
     */
    public function fromPayload(array $payload): AppDocumentTypeConfig
    {
        return new AppDocumentTypeConfig(
            $payload['identifier'],
            $payload['formats'],
            $payload['label'],
            $payload['config'],
        );
    }

    /**
     * An app identifier may neither shadow a core document type nor one already claimed by another app.
     *
     * @param list<DocumentPayload> $documentTypes
     */
    private function assertNoCollision(array $documentTypes, string $appName): void
    {
        /** @var array<string, string> $claimedBy */
        $claimedBy = $this->connection->fetchAllKeyValue(
            'SELECT `name`, `app_name` FROM `app_feature` WHERE `type` = :type AND `app_name` != :appName',
            ['type' => self::TYPE, 'appName' => $appName],
        );

        foreach ($documentTypes as $documentType) {
            $identifier = $documentType['identifier'];

            if (DocumentType::tryFrom($identifier) !== null) {
                throw DocumentV2Exception::documentTypeShadowsCoreType($identifier);
            }

            if (isset($claimedBy[$identifier])) {
                throw DocumentV2Exception::documentTypeAlreadyRegistered($identifier, $claimedBy[$identifier]);
            }
        }
    }

    /**
     * Seeds a global `number_range` (+ type) per app document type so {@see DocumentNumberGenerator}
     * can allocate numbers. Ranges are created once and never removed, so a reinstalled identifier
     * continues its existing sequence instead of re-issuing already-used numbers.
     *
     * @param list<DocumentPayload> $documentTypes
     */
    private function seedNumberRanges(array $documentTypes, Context $context): void
    {
        foreach ($documentTypes as $documentType) {
            $identifier = $documentType['identifier'];
            $technicalName = DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . $identifier;

            $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName));

            if ($this->numberRangeTypeRepository->searchIds($criteria, $context)->firstId() !== null) {
                continue;
            }

            $typeId = Uuid::randomHex();

            $this->numberRangeTypeRepository->create([[
                'id' => $typeId,
                'technicalName' => $technicalName,
                'global' => true,
                'typeName' => $identifier,
            ]], $context);

            $this->numberRangeRepository->create([[
                'id' => Uuid::randomHex(),
                'typeId' => $typeId,
                'global' => true,
                'name' => $identifier,
                'pattern' => '{n}',
                'start' => 1000,
            ]], $context);
        }
    }

    /**
     * Guarantees the shop default locale has a label, falling back to the English or first
     * declared translation, mirroring the manifest translation handling of other app features.
     *
     * @param array<string, string> $label
     *
     * @return array<string, string>
     */
    private static function resolveLabel(array $label, string $defaultLocale): array
    {
        if ($label === [] || \array_key_exists($defaultLocale, $label)) {
            return $label;
        }

        $label[$defaultLocale] = $label[self::FALLBACK_LOCALE] ?? reset($label);

        return $label;
    }
}
