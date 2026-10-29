<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * An app's contribution to the telemetry metric label configuration, parsed from
 * `Resources/config/telemetry.xml` and persisted as one `app_feature` row of type `telemetry` per app
 * (see TelemetryAppFeatureDefinition).
 *
 * @internal only for use by the app-system
 *
 * @phpstan-type LabelValuesEntry array{metric: string, label: string, values: list<string>}
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
     * @param list<LabelValuesEntry> $labelValues
     * @param array<string, array<string, string>> $mappings mapping name → (key → label value)
     */
    public function __construct(
        public readonly array $labelValues = [],
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

        $labelValues = [];
        foreach ($doc->getElementsByTagName('label-values') as $element) {
            $values = [];
            foreach ($element->getElementsByTagName('value') as $value) {
                $values[] = $value->nodeValue ?? '';
            }

            $labelValues[] = [
                'metric' => $element->getAttribute('metric'),
                'label' => $element->getAttribute('label'),
                'values' => array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== ''))),
            ];
        }

        $mappings = [];
        foreach ($doc->getElementsByTagName('mapping') as $element) {
            $name = $element->getAttribute('name');
            foreach ($element->getElementsByTagName('entry') as $entry) {
                $value = $entry->nodeValue ?? '';
                if ($value === '') {
                    continue;
                }

                $mappings[$name][$entry->getAttribute('key')] = $value;
            }
        }

        return new self($labelValues, $mappings);
    }

    /**
     * @param array{labelValues?: list<LabelValuesEntry>, mappings?: array<string, array<string, string>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            labelValues: $data['labelValues'] ?? [],
            mappings: $data['mappings'] ?? [],
        );
    }

    /**
     * @return array{labelValues: list<LabelValuesEntry>, mappings: array<string, array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'labelValues' => $this->labelValues,
            'mappings' => $this->mappings,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->labelValues === [] && $this->mappings === [];
    }
}
