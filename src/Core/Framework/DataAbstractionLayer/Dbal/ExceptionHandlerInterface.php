<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;

interface ExceptionHandlerInterface
{
    public function matchException(\Exception $e, WriteCommandInterface $command): ?\Exception;
}
