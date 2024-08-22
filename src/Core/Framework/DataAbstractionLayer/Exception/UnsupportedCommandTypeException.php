<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class UnsupportedCommandTypeException extends HttpException
{
    public function __construct(WriteCommand $command)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__UNSUPPORTED_COMMAND_TYPE_EXCEPTION',
            'Command of class {{ command }} is not supported by {{ definition }}',
            ['command' => $command::class, 'definition' => $command->getEntityName()]
        );
    }
}
