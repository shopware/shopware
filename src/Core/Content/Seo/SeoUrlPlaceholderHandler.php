<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class SeoUrlPlaceholderHandler implements SeoUrlPlaceholderHandlerInterface
{
    public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        SalesChannelRepositoryInterface $seoUrlRepository
    ) {
        $this->router = $router;
        $this->seoUrlRepository = $seoUrlRepository;
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $name
     */
    public function generate($name, array $parameters = []): string
    {
        $path = $this->router->generate($name, $parameters, RouterInterface::ABSOLUTE_PATH);

        $request = $this->requestStack->getMasterRequest();
        $basePath = $request ? $request->getBasePath() : '';
        $path = $this->removePrefix($path, $basePath);

        return self::DOMAIN_PLACEHOLDER . $path . '#';
    }

    public function replace(string $content, string $host, SalesChannelContext $salesChannelContext): string
    {
        $matches = [];

        if (preg_match_all('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', $content, $matches)) {
            $mapping = $this->createDefaultMapping($matches[0]);
            $seoMapping = $this->createSeoMapping($salesChannelContext, $mapping);
            foreach ($seoMapping as $key => $value) {
                $seoMapping[$key] = $host . '/' . ltrim($value, '/');
            }

            $content = str_replace(array_keys($seoMapping), array_values($seoMapping), $content);
        }

        return $content;
    }

    private function createDefaultMapping(array $matches): array
    {
        $mapping = [];
        foreach ($matches as $match) {
            $mapping[$match] = str_replace(self::DOMAIN_PLACEHOLDER, '', rtrim($match, '#'));
        }

        return $mapping;
    }

    private function createSeoMapping(SalesChannelContext $context, array $mapping): array
    {
        if (empty($mapping)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsAnyFilter('pathInfo', $mapping));
        $criteria->addSorting(new FieldSorting('salesChannelId'));
        $criteria->setTitle('seo-url::replacement');

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $this->seoUrlRepository->search($criteria, $context)->getEntities();

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
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }
}
