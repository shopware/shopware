<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service\Check;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Api\HealthCheck\Model\Result;
use Shopware\Core\Framework\Api\HealthCheck\Model\Status;
use Shopware\Core\Framework\Api\HealthCheck\Service\Check;
use \Redis;

class Cache implements Check
{
    public function __construct(private readonly CacheItemPoolInterface $cacheItemPool)
    {
    }

    public function run(): Result
    {
        $randomKey = bin2hex(random_bytes(3));
        $item = $this->cacheItemPool->getItem(sprintf("health-check%s", $randomKey));
        $item->set('true');
        $saved = $this->cacheItemPool->save($item);
        if (! $saved) {
            return new Result('Cache', Status::Error, 'Items can not be saved in cache.');
        }

        $itemFromCache = $this->cacheItemPool->getItem(sprintf("health-check%s", $randomKey));
        if ($itemFromCache->get() !== 'true') {
            return new Result('Cache', Status::Error, 'Items can not be saved in cache.');
        }

        $isDeleted = $this->cacheItemPool->deleteItem(sprintf("health-check%s", $randomKey));
        if (! $isDeleted) {
            return new Result('Cache', Status::Error, 'Items can not be deleted from cache.');
        }

        return new Result('Cache', Status::Healthy);
    }

    public function dependsOn(): array
    {
        return [];
    }
}
