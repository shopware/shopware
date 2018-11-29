<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class RuleValidator implements WriteCommandValidatorInterface
{
    public function __construct(){}

    /**
     * @param WriteCommandInterface[] $commands
     */
    public function preValidate(array $commands, WriteContext $context): void
    {
        $violationList = new ConstraintViolationList();
        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand || $command->getDefinition() !== RuleConditionDefinition::class) {
                continue;
            }
            $payload = $command->getPayload();
            if(method_exists($payload["type"], "validate")){
                /** @var Rule $type */
                $type = new $payload['type'];
                $violationList->addAll($type->validate($payload));
            }
        }
        $this->tryToThrow($violationList);
    }

    public function postValidate(array $writeCommands, WriteContext $context): void{}

    /**
     * @param ConstraintViolationListInterface $violations
     */
    private function tryToThrow(ConstraintViolationListInterface $violations): void
    {
        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations);
        }
    }
}
