<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Core\Params\UrlParamsSource;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;

#[Package('buyers-experience')]
class MediaUrlPlaceholderHandler implements MediaUrlPlaceholderHandlerInterface
{
    final public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    private const PREFIX = '/mediaId/';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly MediaUrlGenerator $mediaUrlGenerator
    ) {
    }

    public function replace(string $content): string
    {
        return Profiler::trace('media-url-replacer', function () use ($content) {
            $matches = [];

            if (preg_match_all('/' . self::DOMAIN_PLACEHOLDER . preg_quote(self::PREFIX, '/') . '[^#]*#/', $content, $matches)) {
                $seoMapping = $this->createMediaMapping($matches[0]);

                return (string) preg_replace_callback('/' . self::DOMAIN_PLACEHOLDER . preg_quote(self::PREFIX, '/') . '[^#]*#/', static function (array $match) use ($seoMapping) {
                    return $seoMapping[$match[0]] ?? $match[0];
                }, $content);
            }

            return $content;
        });
    }

    /**
     * @param array<string> $matches
     *
     * @return array<string>
     */
    private function createMediaMapping(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $mediaIds = [];
        foreach ($matches as $item) {
            $mediaIds[] = Uuid::fromHexToBytes(substr($item, \strlen(self::DOMAIN_PLACEHOLDER) + \strlen(self::PREFIX), -1));
        }
        $query = new QueryBuilder($this->connection);
        $query->setTitle('media_url::replacement');
        $query->addSelect(['id', 'path', 'updated_at', 'created_at']);
        $query->from('media');
        $query->andWhere('id IN (:mediaIds)');
        $query->setParameter('mediaIds', $mediaIds, ArrayParameterType::BINARY);

        $mediaUrls = $query->executeQuery()->fetchAllAssociative();

        $urlParams = [];
        foreach ($mediaUrls as $record) {
            $id = Uuid::fromBytesToHex($record['id']);
            $urlParams[$id] = new UrlParams(
                $id,
                UrlParamsSource::MEDIA,
                $record['path'],
                new \DateTime($record['updated_at'] ?? $record['created_at']),
            );
        }
        $urls = $this->mediaUrlGenerator->generate($urlParams);

        $mapping = [];
        foreach ($urls as $id => $url) {
            $key = self::DOMAIN_PLACEHOLDER . self::PREFIX . $id . '#';
            $mapping[$key] = $url;
        }

        return $mapping;
    }
}
