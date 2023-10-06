<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class UnsupportedCommandTypeException extends ShopwareHttpException
{
    public function __construct(WriteCommand $command)
    {
        parent::__construct(
            'Command of class {{ command }} is not supported by {{ definition }}',
            ['command' => $command::class, 'definition' => $command->getDefinition()->getEntityName()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UNSUPPORTED_COMMAND_TYPE_EXCEPTION';
    }
}
