<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed in v6.7.0. as it is not used anymore
 */
#[Package('services-settings')]
class XmlFileWriter extends AbstractFileWriter
{
    public function append(Config $config, array $data, int $index): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        if ($index === 0) {
            fwrite($this->buffer, "<?xml version=\"1.0\"?><root>\n");
        }

        $item = new \SimpleXMLElement('<item/>');
        $this->addDataToNode($item, $data);
        $xmlString = $item->asXML();
        if (\is_string($xmlString)) {
            $xml = mb_strstr($xmlString, '<item>');
            if (\is_string($xml)) {
                fwrite($this->buffer, $xml);
            }
        }
    }

    public function finish(Config $config, string $targetPath): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        fwrite($this->buffer, "</root>\n");
        parent::finish($config, $targetPath);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addDataToNode(\SimpleXMLElement $node, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }

            if (\is_array($value)) {
                $child = $node->addChild($key);
                $this->addDataToNode($child, $value);
            } else {
                $node->addChild($key, $this->toString($value));
            }
        }
    }

    private function toString(bool|float|int|string $scalar): string
    {
        if (\is_bool($scalar)) {
            return $scalar ? '1' : '0';
        }

        return (string) $scalar;
    }
}
