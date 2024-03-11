<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Webhook;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Webhooks extends XmlElement
{
    /**
     * @var list<Webhook>
     */
    protected array $webhooks;

    /**
     * @return list<Webhook>
     */
    public function getWebhooks(): array
    {
        return $this->webhooks;
    }

    /**
     * @return list<string>
     */
    public function getUrls(): array
    {
        $urls = [];

        foreach ($this->webhooks as $webhook) {
            $urls[] = $webhook->getUrl();
        }

        return $urls;
    }

    protected static function parse(\DOMElement $element): array
    {
        $webhooks = [];
        foreach ($element->getElementsByTagName('webhook') as $webhook) {
            $webhooks[] = Webhook::fromXml($webhook);
        }

        return ['webhooks' => $webhooks];
    }
}
