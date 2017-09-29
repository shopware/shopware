<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Extension;

use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Symfony\Component\Routing\RequestContext;

class UrlGeneratorExtension extends SeoUrlExtension
{
    /**
     * @var null|RequestContext
     */
    private $context;

    public function __construct(?RequestContext $context)
    {
        $this->context = $context;
    }

    public function seoUrlBasicLoaded(SeoUrlBasicLoadedEvent $event): void
    {
        parent::seoUrlBasicLoaded($event);

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
