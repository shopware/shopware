<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Api\Write\GenericWrittenEvent;

interface IndexerInterface
{
    public function index(\DateTime $timestamp): void;

    public function refresh(GenericWrittenEvent $event): void;
}
