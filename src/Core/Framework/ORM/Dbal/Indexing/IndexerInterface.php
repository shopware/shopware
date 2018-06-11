<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Indexing;

use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;

interface IndexerInterface
{
    public function index(\DateTime $timestamp, string $tenantId): void;

    public function refresh(EntityWrittenContainerEvent $event): void;
}
