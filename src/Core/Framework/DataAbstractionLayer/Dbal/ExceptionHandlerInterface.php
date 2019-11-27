<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;

interface ExceptionHandlerInterface
{
    public function matchException(\Exception $e, WriteCommand $command): ?\Exception;
}
