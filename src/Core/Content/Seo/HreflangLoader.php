<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\Hreflang\HreflangStruct;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class HreflangLoader implements HreflangLoaderInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelDomainRepository;

    public function __construct(
        RouterInterface $router,
        EntityRepositoryInterface $seoUrlRepository,
        EntityRepositoryInterface $salesChannelDomainRepository
    ) {
        $this->router = $router;
        $this->seoUrlRepository = $seoUrlRepository;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    public function load(HreflangLoaderParameter $parameter): HreflangCollection
    {
        $salesChannelContext = $parameter->getSalesChannelContext();

        if (!$salesChannelContext->getSalesChannel()->isHreflangActive()) {
            return new HreflangCollection();
        }

        if ($parameter->getRoute() === 'frontend.home.page') {
            return $this->generateHreflangHome($parameter->getSalesChannelContext());
        }

        $pathInfo = $this->router->generate($parameter->getRoute(), $parameter->getRouteParameters(), RouterInterface::ABSOLUTE_PATH);

        $criteria = new Criteria();
        $criteria->addAssociation('language.locale');
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsFilter('pathInfo', $pathInfo));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannel()->getId()));

        $result = $this->seoUrlRepository->search($criteria, $salesChannelContext->getContext());

        // We need at least two links
        if ($result->getTotal() <= 1) {
            return new HreflangCollection();
        }

        $hreflangCollection = new HreflangCollection();

        /** @var SeoUrlEntity $entity */
        foreach ($result as $entity) {
            foreach ($salesChannelContext->getSalesChannel()->getDomains() as $domain) {
                if ($entity->getLanguageId() === $domain->getLanguageId()) {
                    $entity->setUrl($domain->getUrl() . '/' . $entity->getSeoPathInfo());

                    $hrefLang = new HreflangStruct();
                    $hrefLang->setUrl($domain->getUrl() . '/' . $entity->getSeoPathInfo());
                    $locale = $entity->getLanguage()->getLocale()->getCode();

                    if ($domain->isHreflangUseOnlyLocale()) {
                        $locale = substr($locale, 0, 2);
                    }

                    if ($domain->getId() === $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId()) {
                        $mainLang = clone $hrefLang;
                        $mainLang->setLocale('x-default');
                        $hreflangCollection->add($mainLang);
                    }

                    $hrefLang->setLocale($locale);
                    $hreflangCollection->add($hrefLang);
                }
            }
        }

        return $hreflangCollection;
    }

    protected function generateHreflangHome(SalesChannelContext $salesChannelContext): HreflangCollection
    {
        $collection = new HreflangCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannel()->getId()));
        $criteria->addAssociation('language.locale');

        /** @var SalesChannelDomainEntity[] $domains */
        $domains = $this->salesChannelDomainRepository->search($criteria, $salesChannelContext->getContext());

        if (count($domains) <= 1) {
            return new HreflangCollection();
        }

        foreach ($domains as $domain) {
            $hrefLang = new HreflangStruct();
            $hrefLang->setUrl($domain->getUrl());
            $locale = $domain->getLanguage()->getLocale()->getCode();

            if ($domain->isHreflangUseOnlyLocale()) {
                $locale = substr($locale, 0, 2);
            }

            if ($domain->getId() === $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId()) {
                $mainLang = clone $hrefLang;
                $mainLang->setLocale('x-default');
                $collection->add($mainLang);
            }

            $hrefLang->setLocale($locale);
            $collection->add($hrefLang);
        }

        return $collection;
    }
}
