<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

class XmlFileWriter extends FileWriter implements WriterInterface
{
    public function append(array $data, int $index): void
    {
        if ($index === 0) {
            fwrite($this->buffer, "<?xml version=\"1.0\"?><root>\n");
        }

        $item = new \SimpleXMLElement('<item/>');
        $this->addDataToNode($item, $data);
        $xml = mb_strstr($item->asXML(), '<item>');
        fwrite($this->buffer, $xml);
    }

    public function finish(): void
    {
        fwrite($this->buffer, "</root>\n");
        parent::finish();
    }

    private function addDataToNode(\SimpleXMLElement $node, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }

            if (is_array($value)) {
                $child = $node->addChild($key);
                $this->addDataToNode($child, $value);
            } else {
                $node->addChild($key, $this->toString($value));
            }
        }
    }

    private function toString($scalar): string
    {
        if (is_bool($scalar)) {
            return $scalar ? '1' : '0';
        }

        return (string) $scalar;
    }
}
