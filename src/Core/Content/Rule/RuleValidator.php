<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Exception\InvalidConditionException;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class RuleValidator implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<RuleConditionCollection> $ruleConditionRepository
     * @param EntityRepository<AppScriptConditionCollection> $appScriptConditionRepository
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
        private readonly EntityRepository $ruleConditionRepository,
        private readonly EntityRepository $appScriptConditionRepository
    ) {
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
        $writeException = $event->getExceptions();
        $commands = $event->getCommands();
        $updateQueue = [];

        foreach ($commands as $command) {
            if ($command->getEntityName() !== RuleConditionDefinition::ENTITY_NAME) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $this->validateCondition(null, $command, $writeException, $event->getContext());

                continue;
            }

            if ($command instanceof UpdateCommand) {
                $updateQueue[] = $command;

                continue;
            }

            throw RuleException::unsupportedCommandType($command);
        }

        if (!empty($updateQueue)) {
            $this->validateUpdateCommands($updateQueue, $writeException, $event->getContext());
        }
    }

    private function validateCondition(
        ?RuleConditionEntity $condition,
        WriteCommand $command,
        WriteException $writeException,
        Context $context
    ): void {
        $payload = $command->getPayload();
        $violationList = new ConstraintViolationList();

        $type = $this->getConditionType($condition, $payload);
        if ($type === null) {
            return;
        }

        try {
            $ruleInstance = $this->ruleConditionRegistry->getRuleInstance($type);
        } catch (InvalidConditionException) {
            $violation = $this->buildViolation(
                'This {{ value }} is not a valid condition type.',
                ['{{ value }}' => $type],
                '/type',
                'CONTENT__INVALID_RULE_TYPE_EXCEPTION'
            );
            $violationList->add($violation);
            $writeException->add(new WriteConstraintViolationException($violationList, $command->getPath()));

            return;
        }

        $value = $this->getConditionValue($condition, $payload);

        // add violations when a property is not defined on the rule instance
        $missingProperties = [];
        if (!$ruleInstance instanceof ScriptRule) {
            $missingProperties = array_filter(
                $value,
                static fn (string $key): bool => !property_exists($ruleInstance, $key) && !\array_key_exists($key, $ruleInstance->getConstraints()),
                \ARRAY_FILTER_USE_KEY
            );
        }

        foreach (array_keys($missingProperties) as $missingProperty) {
            $violationList->add(
                $this->buildViolation(
                    'The property "{{ fieldName }}" is not allowed.',
                    ['{{ fieldName }}' => $missingProperty],
                    '/value/' . $missingProperty
                )
            );
        }

        // remove missing properties from value before assigning it to the rule instance
        $value = array_diff_key($value, $missingProperties);

        if ($ruleInstance instanceof ScriptRule) {
            $ruleInstance->assignValues($value);
            $this->setScriptConstraints($ruleInstance, $condition, $payload, $context);
        } else {
            $ruleInstance->assign($value);
        }

        $this->validateConsistence(
            $ruleInstance->getConstraints(),
            $value,
            $violationList,
            $missingProperties
        );

        if ($violationList->count() > 0) {
            $writeException->add(new WriteConstraintViolationException($violationList, $command->getPath()));
        }
    }

    /**
     * @param array<mixed> $payload
     */
    private function getConditionType(?RuleConditionEntity $condition, array $payload): ?string
    {
        $type = $condition?->getType();
        if (\array_key_exists('type', $payload)) {
            $type = $payload['type'];
        }

        return $type;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function getConditionValue(?RuleConditionEntity $condition, array $payload): array
    {
        $value = $condition !== null ? $condition->getValue() : [];
        if (isset($payload['value'])) {
            $value = json_decode((string) $payload['value'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $value ?? [];
    }

    /**
     * @param array<string, array<Constraint>> $fieldValidations
     * @param array<mixed> $payload
     * @param array<string> $missingProperties
     */
    private function validateConsistence(array $fieldValidations, array $payload, ConstraintViolationList $violationList, array $missingProperties): void
    {
        foreach ($fieldValidations as $fieldName => $validations) {
            $violationList->addAll(
                $this->validator->startContext()
                    ->atPath('/value/' . $fieldName)
                    ->validate($payload[$fieldName] ?? null, $validations)
                    ->getViolations()
            );
        }

        foreach ($payload as $fieldName => $_value) {
            if (!\array_key_exists($fieldName, $fieldValidations) && $fieldName !== '_name' && !isset($missingProperties[$fieldName])) {
                $violationList->add(
                    $this->buildViolation(
                        'The property "{{ fieldName }}" is not allowed.',
                        ['{{ fieldName }}' => $fieldName],
                        '/value/' . $fieldName
                    )
                );
            }
        }
    }

    /**
     * @param array<UpdateCommand> $commandQueue
     */
    private function validateUpdateCommands(
        array $commandQueue,
        WriteException $writeException,
        Context $context
    ): void {
        $conditions = $this->getSavedConditions($commandQueue, $context);

        foreach ($commandQueue as $command) {
            $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            $condition = $conditions->get($id);

            $this->validateCondition($condition, $command, $writeException, $context);
        }
    }

    /**
     * @param array<UpdateCommand> $commandQueue
     */
    private function getSavedConditions(array $commandQueue, Context $context): RuleConditionCollection
    {
        $ids = array_map(function ($command) {
            $uuidBytes = $command->getPrimaryKey()['id'];

            return Uuid::fromBytesToHex($uuidBytes);
        }, $commandQueue);

        $criteria = new Criteria($ids);
        $criteria->setLimit(null);

        return $this->ruleConditionRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<int|string> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            null,
            null,
            $code
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function setScriptConstraints(
        ScriptRule $ruleInstance,
        ?RuleConditionEntity $condition,
        array $payload,
        Context $context
    ): void {
        $script = null;
        if (isset($payload['script_id'])) {
            $scriptId = Uuid::fromBytesToHex($payload['script_id']);
            $script = $this->appScriptConditionRepository->search(new Criteria([$scriptId]), $context)->get($scriptId);
        } elseif ($condition && $condition->getAppScriptCondition()) {
            $script = $condition->getAppScriptCondition();
        }

        if (!$script instanceof AppScriptConditionEntity || !\is_array($script->getConstraints())) {
            return;
        }

        $ruleInstance->setConstraints($script->getConstraints());
    }
}
