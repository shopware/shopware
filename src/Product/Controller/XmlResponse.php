<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;

class XmlResponse
{
    /**
     * @var string
     */
    private $childNode;

    public function createResponse(string $rootNode, string $childNode, $arrayData): Response
    {
        $this->childNode = $childNode;
        $xml = $this->arrayToXml($rootNode, $childNode, $arrayData);

        $response = new Response($xml->asXML());
        $response->headers->set('Content-Type', 'xml');

        return $response;
    }

    private function arrayToXml(string $rootNode, string $childNode, $arrayData): SimpleXMLElement
    {
        $this->childNode = $childNode;
        $xml = new SimpleXMLElement('<' . $rootNode . '/>');
        $this->toXml($xml, $arrayData);

        return $xml;
    }

    private function toXml(\SimpleXMLElement $xml, $data): void
    {
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $xmlNode = $xml->addChild($this->childNode);
                $this->toXml($xmlNode, $value);
            } elseif (is_array($value) || is_object($value)) {
                $xmlNode = $xml->addChild($key);
                $this->toXml($xmlNode, $value);
            } else {
                $xml->addChild($key, (string) $value);
            }
        }
    }
}
