<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\Exception\LanguageForeignKeyDeleteException;

class LanguageExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_LATE;
    }

    public function matchException(\Exception $e, WriteCommand $command): ?\Exception
    {
        if ($e->getCode() !== 0) {
            return null;
        }

        if (
            $command instanceof DeleteCommand
            && $command->getDefinition()->getEntityName() === 'language'
            && preg_match('/SQLSTATE\[23000\]:.*(1217|1216).*a foreign key constraint/', $e->getMessage())
        ) {
            $primaryKey = $command->getPrimaryKey();

            return new LanguageForeignKeyDeleteException(isset($primaryKey['id']) ? Uuid::fromBytesToHex($primaryKey['id']) : '', $e);
        }

        return null;
    }
}
