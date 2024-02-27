<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

#[Package('buyers-experience')]
class SeoUrlPlaceholderHandler implements SeoUrlPlaceholderHandlerInterface
{
    final public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param string $name
     * @param array<string, string> $parameters
     */
    public function generate($name, array $parameters = []): string
    {
        $path = $this->router->generate($name, $parameters);

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

                return (string) \preg_replace_callback('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', static fn (array $match) => $seoMapping[$match[0]], $content);
            }

            return $content;
        });
    }

    /**
     * @param array<string, string> $matches
     *
     * @return array<string, string>
     */
    private function createDefaultMapping(array $matches): array
    {
        $mapping = [];
        $placeholder = \strlen(self::DOMAIN_PLACEHOLDER);
        foreach ($matches as $match) {
            // remove self::DOMAIN_PLACEHOLDER from start
            // remove # from end
            $mapping[$match] = substr((string) $match, $placeholder, -1);
        }

        return $mapping;
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return array<string, string>
     */
    private function createSeoMapping(SalesChannelContext $context, array $mapping): array
    {
        if (empty($mapping)) {
            return [];
        }

        $subqueryParts = '';
        $params = [];

        $languageId = Uuid::fromHexToBytes($context->getLanguageId());
        $salesChannelId = Uuid::fromHexToBytes($context->getSalesChannelId());

        foreach ($mapping as $key => $value) {
            $subqueryParts .= '(SELECT id FROM seo_url WHERE path_info = ? AND language_id = ? AND is_canonical = 1 AND is_deleted = 0 AND sales_channel_id = ? LIMIT 1) UNION ALL ';
            $params[] = $value;
            $params[] = $languageId;
            $params[] = $salesChannelId;
        }

        $seoUrls = $this->connection->fetchAllKeyValue($this->getSeoQuery($subqueryParts), $params);

        $missing = [];
        $subqueryParts = '';
        $params = [];

        foreach ($mapping as $key => $value) {
            if (isset($seoUrls[$value])) {
                $mapping[$key] = $seoUrls[$value];
            } else {
                $missing[$key] = $value;
                $subqueryParts .= '(SELECT id FROM seo_url WHERE path_info = ? AND language_id = ? AND is_canonical = 1 AND is_deleted = 0 AND sales_channel_id IS NULL LIMIT 1) UNION ALL ';
                $params[] = $value;
                $params[] = $languageId;
            }
        }

        if (empty($missing)) {
            return $mapping;
        }

        $seoUrls = $this->connection->fetchAllKeyValue($this->getSeoQuery($subqueryParts), $params);

        foreach ($missing as $key => $value) {
            $mapping[$key] = $seoUrls[$value] ?? $value;
        }

        return $mapping;
    }

    private function getSeoQuery(string $subqueryParts): string
    {
        $subqueryParts = rtrim($subqueryParts, 'UNION ALL ');

        return '# seo_url::replacement
        SELECT path_info,seo_path_info FROM seo_url WHERE id IN (SELECT id FROM (' . $subqueryParts . ') as ids)';
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }
}
