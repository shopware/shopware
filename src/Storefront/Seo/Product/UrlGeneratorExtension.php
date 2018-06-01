<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Product;

use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\Deferred;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Storefront\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Storefront\Api\Seo\Event\SeoUrl\SeoUrlBasicLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UrlGeneratorExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {
            return;
        }

        foreach ($event->getSeoUrls() as $seoUrl) {
            $seoUrl->setUrl($request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/' . $seoUrl->getSeoPathInfo());
        }
    }
}
