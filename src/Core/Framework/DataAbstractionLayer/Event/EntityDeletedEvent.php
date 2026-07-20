<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @template IDStructure of string|array<string, string> = string
 *
 * @extends EntityWrittenEvent<IDStructure>
 */
#[Package('framework')]
class EntityDeletedEvent extends EntityWrittenEvent
{
    /**
     * @param list<EntityWriteResult<IDStructure>> $writeResult
     * @param array<mixed> $errors
     */
    public function __construct(
        string $entityName,
        array $writeResult,
        Context $context,
        array $errors = []
    ) {
        parent::__construct($entityName, $writeResult, $context, $errors);

        $this->name = $entityName . '.deleted';
    }
}
