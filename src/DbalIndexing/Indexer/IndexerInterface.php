<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventCollection;

interface IndexerInterface
{
    public function index(TranslationContext $context, \DateTime $timestamp): void;

    public function refresh(NestedEventCollection $events, TranslationContext $context): void;
}
