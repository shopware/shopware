<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Api\Entity\Write\GenericWrittenEvent;

interface IndexerInterface
{
    public function index(\DateTime $timestamp, string $tenantId): void;

    public function refresh(GenericWrittenEvent $event): void;
}
