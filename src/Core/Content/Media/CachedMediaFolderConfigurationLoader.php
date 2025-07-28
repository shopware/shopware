<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsDecorator(MediaFolderConfigurationLoader::class)]
#[AsEventListener(event: MediaFolderConfigurationDefinition::ENTITY_NAME . '.written', method: 'invalidateCache')]
class CachedMediaFolderConfigurationLoader extends AbstractMediaFolderConfigurationLoader
{
    public const string NAME = 'media-folder-configuration';
    public const string CACHE_TAG = 'media-folder-configuration';

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractMediaFolderConfigurationLoader $decorated,
        #[Autowire(service: 'cache.object')]
        private readonly TagAwareAdapterInterface $cache,
        #[Autowire(service: 'logger')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(string $mediaFolderId, ?Context $context = null): ?MediaFolderConfigurationEntity
    {
        $item = $this->cache->getItem($this->generateKey($mediaFolderId, $context));

        try {
            if ($item->isHit() && $item->get()) {
                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $response = $this->decorated->load($mediaFolderId, $context);

        $item = CacheCompressor::compress($item, $response);

        $item->tag([self::CACHE_TAG]);

        $this->cache->save($item);

        return $response;
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }

    private function generateKey(string $mediaFolderId): string
    {
        $parts = [self::NAME, $mediaFolderId];

        return md5(Json::encode($parts));
    }
}
