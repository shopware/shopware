<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;

interface WriteCommandValidatorInterface
{
    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws ConstraintViolationExceptionInterface
     */
    public function preValidate(array $writeCommands, WriteContext $context): void;

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws ConstraintViolationExceptionInterface
     */
    public function postValidate(array $writeCommands, WriteContext $context): void;
}
