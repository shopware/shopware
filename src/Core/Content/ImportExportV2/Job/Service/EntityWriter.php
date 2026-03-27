<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class EntityWriter
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    /**
     * @param list<array<string, mixed>> $payloads
     */
    public function upsert(string $entityName, array $payloads, Context $context): void
    {
        if ($payloads === []) {
            return;
        }

        $repository = $this->definitionInstanceRegistry->getRepository($entityName);
        $repository->upsert($payloads, $context);
    }
}
