<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
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
     * @return array<string>
     */
    public function getUrls(): array
    {
        $urls = [];

        foreach ($this->webhooks as $webhook) {
            $urls[] = $webhook->getUrl();
        }

        return $urls;
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
