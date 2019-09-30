<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Exception\InvalidConditionException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RuleValidator implements EventSubscriberInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var RuleConditionRegistry
     */
    private $ruleConditionRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleConditionRepository;

    public function __construct(
        ValidatorInterface $validator,
        RuleConditionRegistry $ruleConditionRegistry,
        EntityRepositoryInterface $ruleConditionRepository
    ) {
        $this->validator = $validator;
        $this->ruleConditionRegistry = $ruleConditionRegistry;
        $this->ruleConditionRepository = $ruleConditionRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @throws UnsupportedCommandTypeException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violationList = new ConstraintViolationList();
        $commands = $event->getCommands();
        $updateQueue = [];

        foreach ($commands as $command) {
            if ($command->getDefinition()->getClass() !== RuleConditionDefinition::class) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $this->validateCondition(null, $command, $violationList);
                continue;
            }

            if ($command instanceof UpdateCommand) {
                $updateQueue[] = $command;
                continue;
            }

            throw new UnsupportedCommandTypeException($command);
        }

        if (!empty($updateQueue)) {
            $this->validateUpdateCommands($updateQueue, $violationList, $event->getContext());
        }

        if ($violationList->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violationList));
        }
    }

    private function validateCondition(
        ?RuleConditionEntity $condition,
        WriteCommandInterface $command,
        ConstraintViolationList $violationList
    ): void {
        $payload = $command->getPayload();

        $type = $this->getConditionType($condition, $payload);
        if ($type === null) {
            $violationList->add($this->buildInvalidTypeViolation('NULL', $command->getPath()));

            return;
        }

        try {
            $ruleInstance = $this->ruleConditionRegistry->getRuleInstance($type);
        } catch (InvalidConditionException $e) {
            $violationList->add($this->buildInvalidTypeViolation($type, $command->getPath()));

            return;
        }

        $value = $this->getConditionValue($condition, $payload);
        $this->validateConsistence(
            $command->getPath() . '/value',
            $ruleInstance->getConstraints(),
            $value,
            $violationList
        );
    }

    private function getConditionType(?RuleConditionEntity $condition, array $payload): ?string
    {
        $type = $condition !== null ? $condition->getType() : null;
        if (array_key_exists('type', $payload)) {
            $type = $payload['type'];
        }

        return $type;
    }

    private function getConditionValue(?RuleConditionEntity $condition, array $payload): array
    {
        $value = $condition !== null ? $condition->getValue() : [];
        if (isset($payload['value']) && $payload['value'] !== null) {
            $value = json_decode($payload['value'], true);
        }

        return $value;
    }

    private function validateConsistence(string $basePath, array $fieldValidations, array $payload, ConstraintViolationList $violationList): void
    {
        $currentPath = $basePath . '/';
        foreach ($fieldValidations as $fieldName => $validations) {
            $violationList->addAll(
                $this->validator->startContext()
                    ->atPath($currentPath . $fieldName)
                    ->validate($payload[$fieldName] ?? null, $validations)
                    ->getViolations()
            );
        }

        foreach ($payload as $fieldName => $value) {
            if (!array_key_exists($fieldName, $fieldValidations) && $fieldName !== '_name') {
                $violationList->add(
                    $this->buildViolation(
                        'The property "{{ fieldName }}" is not allowed.',
                        ['{{ fieldName }}' => $fieldName],
                        null,
                        $currentPath . $fieldName
                    )
                );
            }
        }
    }

    private function validateUpdateCommands(
        array $commandQueue,
        ConstraintViolationList $violationList,
        Context $context
    ): void {
        $conditions = $this->getSavedConditions($commandQueue, $context);

        foreach ($commandQueue as $command) {
            $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            $condition = $conditions->get($id);

            $this->validateCondition($condition, $command, $violationList);
        }
    }

    private function getSavedConditions(array $commandQueue, Context $context): EntityCollection
    {
        $ids = array_map(function ($command) {
            $uuidBytes = $command->getPrimaryKey()['id'];

            return Uuid::fromBytesToHex($uuidBytes);
        }, $commandQueue);

        $criteria = new Criteria($ids);
        $criteria->setLimit(null);

        return $this->ruleConditionRepository->search($criteria, $context)->getEntities();
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        $root = null,
        ?string $propertyPath = null,
        $invalidValue = null,
        $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            $plural = null,
            $code,
            $constraint = null,
            $cause = null
        );
    }

    private function buildInvalidTypeViolation(string $type, string $basePath)
    {
        return $this->buildViolation(
            'This {{ value }} is not a valid condition type.',
            ['%value%' => $type ?? 'NULL'],
            null,
            $basePath . '/type'
        );
    }
}
