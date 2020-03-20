<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer instead
 */
interface IndexerInterface
{
    public function index(\DateTimeInterface $timestamp): void;

    public function refresh(EntityWrittenContainerEvent $event): void;

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array;

    public static function getName(): string;
}
