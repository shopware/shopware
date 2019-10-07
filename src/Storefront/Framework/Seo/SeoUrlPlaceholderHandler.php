<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class SeoUrlPlaceholderHandler
{
    public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    /**
     * @var Router
     */
    private $router;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack, Router $router, EntityRepositoryInterface $seoUrlRepository)
    {
        $this->router = $router;
        $this->seoUrlRepository = $seoUrlRepository;
        $this->requestStack = $requestStack;
    }

    public function generate($name, $parameters = []): string
    {
        $path = $this->router->generate($name, $parameters, Router::ABSOLUTE_PATH);

        $request = $this->requestStack->getMasterRequest();
        $basePath = $request ? $request->getBasePath() : '';
        $path = $this->removePrefix($path, $basePath);

        return self::DOMAIN_PLACEHOLDER . $path . '#';
    }

    public function replacePlaceholder(Request $request, Response $response): void
    {
        $host = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        $content = $response->getContent();
        $matches = [];

        if (preg_match_all('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', $content, $matches)) {
            $mapping = $this->createDefaultMapping($matches[0]);
            $seoMapping = $this->createSeoMapping($languageId, $salesChannelId, $mapping);
            foreach ($seoMapping as $key => $value) {
                $seoMapping[$key] = $host . '/' . ltrim($value, '/');
            }

            $content = str_replace(array_keys($seoMapping), array_values($seoMapping), $content);
        }

        $response->setContent($content);
    }

    private function createDefaultMapping(array $matches): array
    {
        $mapping = [];
        foreach ($matches as $match) {
            $mapping[$match] = str_replace(self::DOMAIN_PLACEHOLDER, '', rtrim($match, '#'));
        }

        return $mapping;
    }

    private function createSeoMapping(string $languageId, string $salesChannelId, array $mapping): array
    {
        if (empty($mapping)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsAnyFilter('pathInfo', $mapping));
        $criteria->addSorting(new FieldSorting('salesChannelId'));

        $seoUrls = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var SeoUrlEntity $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            $seoPathInfo = trim($seoUrl->getSeoPathInfo());
            if ($seoPathInfo === '') {
                continue;
            }
            $key = self::DOMAIN_PLACEHOLDER . $seoUrl->getPathInfo() . '#';
            $mapping[$key] = $seoPathInfo;
        }

        return $mapping;
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return substr($subject, strlen($prefix));
    }
}
