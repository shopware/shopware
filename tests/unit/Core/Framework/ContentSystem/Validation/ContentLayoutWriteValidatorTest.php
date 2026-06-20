<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutTreeDecoder;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContentLayoutWriteValidator::class)]
class ContentLayoutWriteValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ContentLayoutDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    #[TestDox('records an invalid_config violation when the layout tree is an undecodable client defect')]
    public function testRecordsInvalidConfigViolationWhenLayoutTreeIsUndecodableClientDefect(): void
    {
        $expected = ContentSystemException::invalidFieldValueType('layout', 'array', 'string');

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException($expected);

        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $validator = new ContentLayoutWriteValidator(
            $gate,
            new ViolationConstraintMapper(),
            $decoder,
            []
        );

        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand(
                    $this->registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
                    ['id' => $id, ContentLayoutDefinition::LAYOUT_FIELD => 'not-decodable'],
                    ['id' => $id],
                    static::createStub(EntityExistence::class),
                    '/update'
                ),
            ]
        );

        $validator->preValidate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);

        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violations = $exception->getViolations();
        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertSame(ViolationCode::InvalidConfig->value, $violation->getCode());
        static::assertSame($expected->getMessage(), $violation->getMessage());
    }

    #[TestDox('rethrows a non-client-defect decode failure unchanged')]
    public function testRethrowsNonClientDefectDecodeFailureUnchanged(): void
    {
        $expected = ContentSystemException::invalidMapKey('someMap', 'int');

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException($expected);

        $validator = new ContentLayoutWriteValidator(
            static::createStub(LayoutGate::class),
            new ViolationConstraintMapper(),
            $decoder,
            []
        );

        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand(
                    $this->registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
                    ['id' => $id, ContentLayoutDefinition::LAYOUT_FIELD => 'not-decodable'],
                    ['id' => $id],
                    static::createStub(EntityExistence::class),
                    '/update'
                ),
            ]
        );

        $this->expectExceptionObject($expected);

        $validator->preValidate($event);
    }
}
