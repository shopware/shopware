<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\Exception\LanguageForeignKeyDeleteException;

class LanguageExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_LATE;
    }

    /**
     * @internal (flag:FEATURE_NEXT_16640) - second parameter WriteCommand $command will be removed
     */
    public function matchException(\Exception $e, ?WriteCommand $command = null): ?\Exception
    {
        if ($e->getCode() !== 0) {
            return null;
        }
        if (!Feature::isActive('FEATURE_NEXT_16640')
            && $command instanceof DeleteCommand
            && $command->getDefinition()->getEntityName() !== LanguageDefinition::ENTITY_NAME) {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*(1217|1216).*a foreign key constraint/', $e->getMessage())) {
            $formattedKey = '';
            if (!Feature::isActive('FEATURE_NEXT_16640')) {
                $primaryKey = $command->getPrimaryKey();
                $formattedKey = isset($primaryKey['id']) ? Uuid::fromBytesToHex($primaryKey['id']) : '';
            }

            return new LanguageForeignKeyDeleteException($formattedKey, $e);
        }

        return null;
    }
}
