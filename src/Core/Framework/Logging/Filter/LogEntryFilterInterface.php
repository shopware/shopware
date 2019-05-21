<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\Filter;

use Shopware\Core\Framework\Event\BusinessEventInterface;

interface LogEntryFilterInterface
{
    public function getSupportedEvents(): array;

    // todo context? acl?
    public function parseEntry(): array;

    public function filterEventData(BusinessEventInterface $event): array;
}
