<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\Exception\LogNotWritableException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Validation\ConstraintViolationExceptionInterface;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;

class ImportExportLogValidator implements WriteCommandValidatorInterface
{
    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws LogNotWritableException
     */
    public function preValidate(array $writeCommands, WriteContext $context): void
    {
        $ids = [];
        foreach ($writeCommands as $command) {
            if ($command->getDefinition()->getClass() === ImportExportLogDefinition::class
                && $command instanceof WriteCommandInterface
                && $context->getContext()->getScope() !== Context::SYSTEM_SCOPE
            ) {
                $ids[] = $command->getPrimaryKey()['id'];
            }
        }

        if (!empty($ids)) {
            throw new LogNotWritableException($ids);
        }
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws ConstraintViolationExceptionInterface
     */
    public function postValidate(array $writeCommands, WriteContext $context): void
    {
        // Nothing
    }
}
