<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\Hreflang\HreflangStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @package sales-channel
 */
class HreflangLoader implements HreflangLoaderInterface
{
    private RouterInterface $router;

    private EntityRepository $salesChannelDomainRepository;

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        EntityRepository $salesChannelDomainRepository,
        Connection $connection
    ) {
        $this->router = $router;
        $this->connection = $connection;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    public function load(HreflangLoaderParameter $parameter): HreflangCollection
    {
        $salesChannelContext = $parameter->getSalesChannelContext();

        if (!$salesChannelContext->getSalesChannel()->isHreflangActive()) {
            return new HreflangCollection();
        }

        $domains = $this->fetchSalesChannelDomains($salesChannelContext->getSalesChannel()->getId());

        if ($parameter->getRoute() === 'frontend.home.page') {
            return $this->getHreflangForHomepage($domains, $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId());
        }

        $pathInfo = $this->router->generate($parameter->getRoute(), $parameter->getRouteParameters(), RouterInterface::ABSOLUTE_PATH);

        $languageToDomainMapping = $this->getLanguageToDomainMapping($domains);
        $seoUrls = $this->fetchSeoUrls($pathInfo, $salesChannelContext->getSalesChannel()->getId(), array_keys($languageToDomainMapping));

        // We need at least two links
        if (\count($seoUrls) <= 1) {
            return new HreflangCollection();
        }

        $hreflangCollection = new HreflangCollection();

        /** @var array{seoPathInfo: string, languageId: string} $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
            foreach ($languageToDomainMapping[$seoUrl['languageId']] as $domain) {
                $this->addHreflangForDomain(
                    $domain,
                    $seoUrl,
                    $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId(),
                    $hreflangCollection
                );
            }
        }

        return $hreflangCollection;
    }

    /**
     * @deprecated tag:v6.5.0 will be removed, use `load()` with route='frontend.home.page'
     */
    protected function generateHreflangHome(SalesChannelContext $salesChannelContext): HreflangCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'load()')
        );

        $collection = new HreflangCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannel()->getId()));
        $criteria->addAssociation('language.locale');

        /** @var SalesChannelDomainEntity[] $domains */
        $domains = $this->salesChannelDomainRepository->search($criteria, $salesChannelContext->getContext());

        if (\count($domains) <= 1) {
            return new HreflangCollection();
        }

        foreach ($domains as $domain) {
            $hrefLang = new HreflangStruct();
            $hrefLang->setUrl($domain->getUrl());
            $locale = $domain->getLanguage()->getLocale()->getCode();

            if ($domain->isHreflangUseOnlyLocale()) {
                $locale = mb_substr($locale, 0, 2);
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

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $domains
     */
    private function getHreflangForHomepage(array $domains, ?string $defaultDomainId): HreflangCollection
    {
        $collection = new HreflangCollection();

        if (\count($domains) <= 1) {
            return new HreflangCollection();
        }

        /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
        foreach ($domains as $domain) {
            $this->addHreflangForDomain(
                $domain,
                null,
                $defaultDomainId,
                $collection
            );
        }

        return $collection;
    }

    /**
     * @return list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}>
     */
    private function fetchSalesChannelDomains(string $salesChannelId): array
    {
        /** @var list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `domain`.`language_id` AS languageId,
                          `domain`.`id` AS id,
                          `domain`.`url` AS url,
                          `domain`.`hreflang_use_only_locale` AS onlyLocale,
                          `locale`.`code` AS locale
            FROM `sales_channel_domain` AS `domain`
            INNER JOIN `language` ON `language`.`id` = `domain`.`language_id`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `domain`.`sales_channel_id` = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $result;
    }

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string}> $domains
     *
     * @return array<string, list<array{languageId: string, id: string, url: string, locale: string}>>
     */
    private function getLanguageToDomainMapping(array $domains): array
    {
        $mapping = [];

        foreach ($domains as $domain) {
            $mapping[$domain['languageId']][] = $domain;
        }

        return $mapping;
    }

    /**
     * @param array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain
     * @param array{seoPathInfo: string, languageId: string}|null $seoUrl
     */
    private function addHreflangForDomain(
        array $domain,
        ?array $seoUrl,
        ?string $defaultDomainId,
        HreflangCollection $collection
    ): void {
        $hrefLang = new HreflangStruct();

        $hrefLang->setUrl($domain['url']);
        if ($seoUrl) {
            $hrefLang->setUrl($domain['url'] . '/' . $seoUrl['seoPathInfo']);
        }
        $locale = $domain['locale'];

        if ($domain['onlyLocale']) {
            $locale = mb_substr($locale, 0, 2);
        }

        if (Uuid::fromBytesToHex($domain['id']) === $defaultDomainId) {
            $mainLang = clone $hrefLang;
            $mainLang->setLocale('x-default');
            $collection->add($mainLang);
        }

        $hrefLang->setLocale($locale);
        $collection->add($hrefLang);
    }

    /**
     * @param array<string> $languageIds
     *
     * @return list<array{seoPathInfo: string, languageId: string}>
     */
    private function fetchSeoUrls(string $pathInfo, string $salesChannelId, array $languageIds): array
    {
        /** @var list<array{seoPathInfo: string, languageId: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `seo_path_info` AS seoPathInfo, `language_id` AS languageId
            FROM `seo_url`
            WHERE `path_info` = :pathInfo AND `is_canonical` = 1 AND
                  `sales_channel_id` = :salesChannelId AND `language_id` IN (:languageIds)',
            ['pathInfo' => $pathInfo, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId), 'languageIds' => $languageIds],
            ['languageIds' => Connection::PARAM_STR_ARRAY]
        );

        return $result;
    }
}
