<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Storefront;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Storefront extends XmlElement
{
    protected int $templateLoadPriority = 0;

    /**
     * @var list<SeoUrl>
     */
    protected array $seoUrls = [];

    /**
     * @var list<EntitySeoUrl>
     */
    protected array $entitySeoUrls = [];

    public function getTemplateLoadPriority(): int
    {
        return $this->templateLoadPriority;
    }

    /**
     * @return list<SeoUrl>
     */
    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }

    /**
     * @return list<EntitySeoUrl>
     */
    public function getEntitySeoUrls(): array
    {
        return $this->entitySeoUrls;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];
        $seoUrls = [];
        $entitySeoUrls = [];

        foreach ($element->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($node->tagName === 'template-load-priority') {
                $values['templateLoadPriority'] = XmlUtils::phpize($node->textContent);
            }

            if ($node->tagName === 'seo-url') {
                $seoUrls[] = SeoUrl::fromXml($node);
            }

            if ($node->tagName === 'entity-seo-url') {
                $entitySeoUrls[] = EntitySeoUrl::fromXml($node);
            }
        }

        $values['seoUrls'] = $seoUrls;
        $values['entitySeoUrls'] = $entitySeoUrls;

        return $values;
    }
}
