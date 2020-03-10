<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;

class ProductStreamIndexer implements IndexerInterface
{
    public function index(\DateTimeInterface $timestamp): void
    {
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        return null;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    public static function getName(): string
    {
        return 'Swag.ProductStreamIndexer';
    }
}
