<?php declare(strict_types=1);

namespace Shopware\Seo\Extension;

use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Api\Seo\Event\SeoUrl\SeoUrlBasicLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RequestContext;

class UrlGeneratorExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    /**
     * @var null|RequestContext
     */
    private $context;

    public function __construct(?RequestContext $context)
    {
        $this->context = $context;
    }

    public function extendFields(FieldCollection $collection)
    {
        $collection->add(
            (new StringField('url', 'url'))->setFlags(new Deferred(), new ReadOnly())
        );
    }

    public function getDefinitionClass(): string
    {
        return SeoUrlDefinition::class;
    }

    public static function getSubscribedEvents()
    {
        return [
            SeoUrlBasicLoadedEvent::NAME => 'seoUrlBasicLoaded',
        ];
    }

    public function seoUrlBasicLoaded(SeoUrlBasicLoadedEvent $event): void
    {
        if (!$this->context) {
            return;
        }

        foreach ($event->getSeoUrls() as $seoUrl) {
            $url = implode('/', array_filter([
                trim($this->context->getBaseUrl(), '/'),
                trim($seoUrl->getSeoPathInfo(), '/'),
            ]));

            $url = sprintf('%s://%s/%s', $this->context->getScheme(), $this->context->getHost(), $url);
            $seoUrl->setUrl($url);
        }
    }
}
