<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentBaseConfigValidator::class)]
class DocumentBaseConfigValidatorTest extends TestCase
{
    private Context $context;

    private DocumentBaseConfigValidator $documentBaseConfigValidator;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->documentBaseConfigValidator = new DocumentBaseConfigValidator(
            new MockClock('2026-01-01 12:00:00'),
        );
        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [DocumentBaseConfigDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testValidateWithNoConfigKeyShouldBeValid(): void
    {
        $event = $this->createEventWithValue([]);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithEmptyConfigKeyShouldBeValid(): void
    {
        $event = $this->createEventWithValue(['config' => null]);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithSimpleDateModifierShouldBeValid(): void
    {
        $payload = $this->createPayload('+30 day');
        $event = $this->createEventWithValue($payload);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithNullShouldBeValid(): void
    {
        $payload = $this->createPayload(null);
        $event = $this->createEventWithValue($payload);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithEmptyStringShouldBeValid(): void
    {
        $payload = $this->createPayload('');
        $event = $this->createEventWithValue($payload);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithInvalidValueShouldBeInvalid(): void
    {
        $payload = $this->createPayload('anyInvalidValue');
        $event = $this->createEventWithValue($payload);

        $this->documentBaseConfigValidator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('DOCUMENT_BASE_CONFIG_INVALID_PAYMENT_DUE_DATE', $exception->getViolations()->get(0)->getCode());
    }

    /**
     * @return array<string, string|null>
     */
    private function createPayload(?string $value): array
    {
        return ['config' => \json_encode(['paymentDueDate' => $value], \JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createEventWithValue(array $payload): PreWriteValidationEvent
    {
        $writeCommand = new UpdateCommand(
            $this->definitionInstanceRegistry->get(DocumentBaseConfigDefinition::class),
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        return new PreWriteValidationEvent(
            WriteContext::createFromContext($this->context),
            [$writeCommand]
        );
    }
}
