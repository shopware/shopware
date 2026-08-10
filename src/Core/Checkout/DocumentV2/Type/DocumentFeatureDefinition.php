<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

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
final class DocumentFeatureDefinition implements AppFeatureDefinition
{
    final public const TYPE = 'document';

    private const FALLBACK_LOCALE = 'en-GB';

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

    /**
     * @return DocumentPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        \assert($declared instanceof AppDocumentTypeConfig);

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
