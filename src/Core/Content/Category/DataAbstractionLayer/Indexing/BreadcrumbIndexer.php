<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater instead
 */
class BreadcrumbIndexer implements IndexerInterface
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

    public function update(array $ids, Context $context): void
    {
    }

    public static function getName(): string
    {
        return 'Swag.BreadcrumbIndexer';
    }
}
