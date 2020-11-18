<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Webhooks extends XmlElement
{
    /**
     * @var Webhook[]
     */
    protected $webhooks = [];

    private function __construct(array $webhooks)
    {
        $this->webhooks = $webhooks;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseWebhooks($element));
    }

    /**
     * @return Webhook[]
     */
    public function getWebhooks(): array
    {
        return $this->webhooks;
    }

    /**
     * @return Webhook[]
     */
    private static function parseWebhooks(\DOMElement $element): array
    {
        $webhooks = [];
        foreach ($element->getElementsByTagName('webhook') as $webhook) {
            $webhooks[] = Webhook::fromXml($webhook);
        }

        return $webhooks;
    }
}
