<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use Shopware\Api\ResponseEnvelope;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;

class XmlResponse extends Response
{
    public function __construct($content = '', $status = 200, array $headers = [], string $rootNode = null, string $childNode = null)
    {
        parent::__construct('', $status, $headers);

        if ($content instanceof ResponseEnvelope) {
            $content = json_decode(json_encode($content), true);
        }

        $xml = $this->arrayToXml($rootNode, $childNode, $content);

        $this->headers->set('Content-Type', 'application/xml');
        $this->setContent($xml->asXML());
    }

    public static function createXmlResponse(string $rootNode, string $childNode, $arrayData, $status = 200, array $headers = []): self
    {
        return new self($arrayData, $status, $headers, $rootNode, $childNode);
    }

    private function arrayToXml(string $rootNode, string $childNode, $arrayData): SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<' . $rootNode . '/>');
        $this->toXml($xml, $arrayData, $childNode);

        return $xml;
    }

    private function toXml(\SimpleXMLElement $xml, $data, string $childNode): void
    {
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $xmlNode = $xml->addChild($childNode);
                $this->toXml($xmlNode, $value, $childNode);
            } elseif (is_array($value) || is_object($value)) {
                $xmlNode = $xml->addChild($key);
                $this->toXml($xmlNode, $value, $childNode);
            } else {
                $xml->addChild($key, (string) $value);
            }
        }
    }
}
