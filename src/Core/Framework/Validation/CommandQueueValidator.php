<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;

class CommandQueueValidator implements WriteCommandValidatorInterface
{
    /**
     * @var WriteCommandValidatorInterface[]
     */
    private $validators;

    public function __construct(iterable $validators)
    {
        foreach ($validators as $validator) {
            if (!$validator instanceof WriteCommandValidatorInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Validator \'%s\'is not an instance of %s',
                        \get_class($validator),
                        WriteCommandValidatorInterface::class
                    )
                );
            }
            $this->validators[] = $validator;
        }
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws WriteStackException
     */
    public function postValidate(array $writeCommands, WriteContext $context): void
    {
        $exceptionStack = new FieldExceptionStack();

        foreach ($this->validators as $validators) {
            try {
                $validators->postValidate($writeCommands, $context);
            } catch (ConstraintViolationException $exception) {
                $exceptionStack->add($exception);
            }
        }

        $exceptionStack->tryToThrow();
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws WriteStackException
     */
    public function preValidate(array $writeCommands, WriteContext $context): void
    {
        $exceptionStack = new FieldExceptionStack();

        foreach ($this->validators as $validators) {
            try {
                $validators->preValidate($writeCommands, $context);
            } catch (ConstraintViolationException $exception) {
                $exceptionStack->add($exception);
            }
        }

        $exceptionStack->tryToThrow();
    }
}
