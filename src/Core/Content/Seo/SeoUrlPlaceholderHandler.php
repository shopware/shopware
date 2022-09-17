<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use function preg_replace_callback;

class SeoUrlPlaceholderHandler implements SeoUrlPlaceholderHandlerInterface
{
    public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    private RouterInterface $router;

    private RequestStack $requestStack;

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        Connection $connection
    ) {
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
    }

    /**
     * @param string $name
     */
    public function generate($name, array $parameters = []): string
    {
        $path = $this->router->generate($name, $parameters, RouterInterface::ABSOLUTE_PATH);

        $request = $this->requestStack->getMainRequest();
        $basePath = $request ? $request->getBasePath() : '';
        $path = $this->removePrefix($path, $basePath);

        return self::DOMAIN_PLACEHOLDER . $path . '#';
    }

    public function replace(string $content, string $host, SalesChannelContext $context): string
    {
        return Profiler::trace('seo-url-replacer', function () use ($content, $host, $context) {
            $matches = [];

            if (preg_match_all('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', $content, $matches)) {
                $mapping = $this->createDefaultMapping($matches[0]);
                $seoMapping = $this->createSeoMapping($context, $mapping);
                foreach ($seoMapping as $key => $value) {
                    $seoMapping[$key] = $host . '/' . ltrim($value, '/');
                }

                return (string) preg_replace_callback('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', static function (array $match) use ($seoMapping) {
                    return $seoMapping[$match[0]];
                }, $content);
            }

            return $content;
        });
    }

    private function createDefaultMapping(array $matches): array
    {
        $mapping = [];
        foreach ($matches as $match) {
            // remove self::DOMAIN_PLACEHOLDER from start
            // remove # from end
            $mapping[$match] = substr($match, 32, -1);
        }

        return $mapping;
    }

    private function createSeoMapping(SalesChannelContext $context, array $mapping): array
    {
        if (empty($mapping)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();
        $query->addSelect(['seo_path_info', 'path_info']);

        $query->from('seo_url');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.path_info IN (:pathInfo)');
        $query->andWhere('seo_url.language_id = :languageId');
        $query->andWhere('seo_url.sales_channel_id = :salesChannelId OR seo_url.sales_channel_id IS NULL');
        $query->andWhere('is_deleted = 0');
        $query->setParameter('pathInfo', $mapping, Connection::PARAM_STR_ARRAY);
        $query->setParameter('languageId', Uuid::fromHexToBytes($context->getContext()->getLanguageId()));
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($context->getSalesChannelId()));

        $seoUrls = $query->execute()->fetchAll();
        foreach ($seoUrls as $seoUrl) {
            $seoPathInfo = trim($seoUrl['seo_path_info']);
            if ($seoPathInfo === '') {
                continue;
            }
            $key = self::DOMAIN_PLACEHOLDER . $seoUrl['path_info'] . '#';
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
