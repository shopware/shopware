<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Storefront\Framework\Seo\SeoUrlDefinition;
use Shopware\Storefront\Framework\Seo\SeoUrlEntity;
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

    public function extendFields(FieldCollection $collection): void
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
            'seo_url.loaded' => 'seoUrlBasicLoaded',
        ];
    }

    public function seoUrlBasicLoaded(EntityLoadedEvent $event): void
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {
            return;
        }

        /** @var SeoUrlEntity $seoUrl */
        foreach ($event->getEntities() as $seoUrl) {
            $seoUrl->setUrl($request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/' . $seoUrl->getSeoPathInfo());
        }
    }
}
