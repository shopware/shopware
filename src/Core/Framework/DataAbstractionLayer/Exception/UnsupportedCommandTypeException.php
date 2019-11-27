<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\ShopwareHttpException;

class UnsupportedCommandTypeException extends ShopwareHttpException
{
    public function __construct(WriteCommand $command)
    {
        parent::__construct(
            'Command of class {{ command }} is not supported by {{ definition }}',
            ['command' => \get_class($command), 'definition' => $command->getDefinition()->getClass()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UNSUPPORTED_COMMAND_TYPE_EXCEPTION';
    }
}
