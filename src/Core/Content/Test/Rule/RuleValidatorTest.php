<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class RuleValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    /**
     * @var WriteContext
     */
    private $context;

    /**
     * @var RuleConditionRegistry|MockObject
     */
    private $conditionRegistry;

    /**
     * @var RuleConditionDefinition
     */
    private $ruleConditionDefinition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());
        $symfonyValidator = $this->getContainer()->get('validator');
        $this->conditionRegistry = $this->createMock(RuleConditionRegistry::class);
        $this->ruleValidator = new RuleValidator($symfonyValidator, $this->conditionRegistry);
        $this->ruleConditionDefinition = $this->getContainer()->get(RuleConditionDefinition::class);
    }

    public function testInsertInvalidType(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            $this->ruleConditionDefinition,
            ['type' => 'false'],
            ['id' => $id],
            $this->createMock(EntityExistence::class)
        );
        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This "type" value (false) is invalid.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateInvalidType(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            $this->ruleConditionDefinition,
            ['id' => $id],
            ['type' => 'false'],
            $this->createMock(EntityExistence::class)
        );
        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This "type" value (false) is invalid.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertRequiredField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            $this->ruleConditionDefinition,
            ['type' => 'type'],
            ['id' => $id],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This value should not be blank.', $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateRequiredField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            $this->ruleConditionDefinition,
            ['id' => $id],
            ['type' => 'type'],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This value should not be blank.', $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertOptionalField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            $this->ruleConditionDefinition,
            ['type' => 'type'],
            ['id' => $id],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['=', '!='])]]
        );
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $this->ruleValidator->preValidate($event);
        $event->getExceptions()->tryToThrow();
    }

    public function testUpdateOptionalField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            $this->ruleConditionDefinition,
            ['id' => $id],
            ['type' => 'type'],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['=', '!='])]]
        );
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $this->ruleValidator->preValidate($event);
        $event->getExceptions()->tryToThrow();
    }

    public function testInsertWithOptionalField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            $this->ruleConditionDefinition,
            ['type' => 'type', 'value' => json_encode(['field' => 'invalid'])],
            ['id' => $id],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['valid'])]]
        );
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'The value you selected is not a valid choice.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateWithOptionalField(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            $this->ruleConditionDefinition,
            ['id' => $id],
            ['type' => 'type', 'value' => json_encode(['field' => 'invalid'])],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['valid'])]]
        );
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $event = new PreWriteValidationEvent($this->context, $commands);
            $this->ruleValidator->preValidate($event);
            $event->getExceptions()->tryToThrow();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertCount(1, $stackException->getExceptions());
            $constraintViolationException = $stackException->getExceptions()[0];
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'The value you selected is not a valid choice.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertValid(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            $this->ruleConditionDefinition,
            ['type' => 'type', 'value' => json_encode(['field' => 'valid'])],
            ['id' => $id],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $this->ruleValidator->preValidate($event);
        $event->getExceptions()->tryToThrow();
    }

    public function testUpdateValid(): void
    {
        $id = Uuid::randomBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            $this->ruleConditionDefinition,
            ['id' => $id],
            ['type' => 'type', 'value' => json_encode(['field' => 'valid'])],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects(static::once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects(static::once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects(static::once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $this->ruleValidator->preValidate($event);
        $event->getExceptions()->tryToThrow();
    }
}
