<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class RuleException extends HttpException
{
    public static function unsupportedCommandType(WriteCommand $command): HttpException
    {
        return new UnsupportedCommandTypeException($command);
    }
}
