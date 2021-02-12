<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class LandingPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.landing.page';
    public const DEFAULT_TEMPLATE = '{{ landingPage.translated.url }}';

    /**
     * @var LandingPageDefinition
     */
    private $landingPageDefinition;

    public function __construct(LandingPageDefinition $landingPageDefinition)
    {
        $this->landingPageDefinition = $landingPageDefinition;
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->landingPageDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true
        );
    }

    public function prepareCriteria(Criteria $criteria): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
    }

    public function getMapping(Entity $landingPage, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$landingPage instanceof LandingPageEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        $landingPageJson = $landingPage->jsonSerialize();

        return new SeoUrlMapping(
            $landingPage,
            ['landingPageId' => $landingPage->getId()],
            [
                'landingPage' => $landingPageJson,
            ]
        );
    }
}
