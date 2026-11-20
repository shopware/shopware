<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
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
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Rule\RuleValidatorTest
 */
#[Package('fundamentals@after-sales')]
class RuleValidator implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<AppScriptConditionCollection> $appScriptConditionRepository
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
        private readonly EntityRepository $appScriptConditionRepository,
        private readonly WriteOutcome $writeOutcome,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $conditions = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(RuleConditionDefinition::ENTITY_NAME),
            ['type', 'value', 'script_id']
        );

        foreach ($conditions as $condition) {
            $this->validateCondition($condition, $event->getExceptions(), $event->getContext());
        }
    }

    private function validateCondition(OutcomeRow $condition, WriteException $writeException, Context $context): void
    {
        $violationList = new ConstraintViolationList();

        $type = $condition->getResultingValue('type');
        if (!\is_string($type)) {
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
            $writeException->add(new WriteConstraintViolationException($violationList, $condition->path));

            return;
        }

        $value = $this->getConditionValue($condition);

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
            $this->setScriptConstraints($ruleInstance, $condition, $context);
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
            $writeException->add(new WriteConstraintViolationException($violationList, $condition->path));
        }
    }

    /**
     * @return array<mixed>
     */
    private function getConditionValue(OutcomeRow $condition): array
    {
        $value = $condition->getResultingValue('value');

        return \is_string($value) ? json_decode($value, true, 512, \JSON_THROW_ON_ERROR) ?? [] : [];
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
     * @param array<string> $parameters
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

    private function setScriptConstraints(ScriptRule $ruleInstance, OutcomeRow $condition, Context $context): void
    {
        $scriptId = $condition->getResultingValue('script_id');
        if (!\is_string($scriptId)) {
            return;
        }

        $scriptId = Uuid::fromBytesToHex($scriptId);
        $script = $this->appScriptConditionRepository->search(new Criteria([$scriptId]), $context)->getEntities()->get($scriptId);
        if ($script === null || !\is_array($script->getConstraints())) {
            return;
        }

        $ruleInstance->setConstraints($script->getConstraints());
    }
}
