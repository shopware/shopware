<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Exception\LanguageOfOrderDeleteException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Exception $e, WriteCommand $command): ?\Exception
    {
        if ($e->getCode() !== 0 || $command->getDefinition()->getEntityName() !== 'language') {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1451.*a foreign key constraint.*order.*CONSTRAINT `fk.language_id`/', $e->getMessage())) {
            $primaryKey = $command->getPrimaryKey();

            return new LanguageOfOrderDeleteException(isset($primaryKey['id']) ? Uuid::fromBytesToHex($primaryKey['id']) : '', $e);
        }

        return null;
    }
}
