<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use Shopware\Core\Content\ImportExport\Struct\Config;

class XmlFileWriter extends AbstractFileWriter
{
    public function append(Config $config, array $data, int $index): void
    {
        if ($index === 0) {
            fwrite($this->buffer, "<?xml version=\"1.0\"?><root>\n");
        }

        $item = new \SimpleXMLElement('<item/>');
        $this->addDataToNode($item, $data);
        $xml = mb_strstr($item->asXML(), '<item>');
        fwrite($this->buffer, $xml);
    }

    public function finish(Config $config, string $targetPath): void
    {
        fwrite($this->buffer, "</root>\n");
        parent::finish($config, $targetPath);
    }

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

    /**
     * @param bool|float|int|string $scalar
     */
    private function toString($scalar): string
    {
        if (\is_bool($scalar)) {
            return $scalar ? '1' : '0';
        }

        return (string) $scalar;
    }
}
