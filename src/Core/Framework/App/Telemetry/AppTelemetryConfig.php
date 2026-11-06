<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * The identifiers an app claims for the telemetry label mappings, parsed from
 * `Resources/config/telemetry.xml` and persisted as one `app_feature` row of type `telemetry` per app
 * (see TelemetryAppFeatureDefinition).
 *
 * An app claims keys, never label values: a claimed key resolves to a value derived from the app's name,
 * so cardinality grows by at most one value per installed app, never by what an app declares.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppTelemetryConfig implements AppFeatureConfig
{
    /**
     * An app declares at most one telemetry contribution, so the feature-instance name is constant.
     */
    public const NAME = 'telemetry';

    private const XSD_FILE = __DIR__ . '/Schema/telemetry-1.0.xsd';

    /**
     * @param array<string, list<string>> $mappings mapping name → claimed keys (exact, or prefix with trailing `*`)
     */
    public function __construct(
        public readonly array $mappings = [],
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @throws AppException when the file does not parse or violates the schema
     */
    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        $mappings = [];
        foreach ($doc->getElementsByTagName('mapping') as $element) {
            $keys = [];
            foreach ($element->getElementsByTagName('key') as $key) {
                $keys[] = trim($key->nodeValue ?? '');
            }

            $keys = array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
            if ($keys !== []) {
                $mappings[$element->getAttribute('name')] = $keys;
            }
        }

        return new self($mappings);
    }

    /**
     * @param array{mappings?: array<string, list<string>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['mappings'] ?? []);
    }

    /**
     * @return array{mappings: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return ['mappings' => $this->mappings];
    }

    public function isEmpty(): bool
    {
        return $this->mappings === [];
    }
}
