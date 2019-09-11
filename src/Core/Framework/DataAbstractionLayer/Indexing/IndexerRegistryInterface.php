<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

interface IndexerRegistryInterface
{
    public function index(\DateTimeInterface $timestamp): void;

    public function refresh(EntityWrittenContainerEvent $event): void;

    public function partial(?string $lastIndexer, ?array $lastId, \DateTimeInterface $timestamp): ?IndexerRegistryPartialResult;
}
