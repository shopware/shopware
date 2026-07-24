<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Clock\ClockInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private DocumentBaseConfigValidator $documentBaseConfigValidator;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->documentBaseConfigValidator = new DocumentBaseConfigValidator(
            $this->getContainer()->get(ClockInterface::class),
        );
        $this->definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
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
     * @return array<string, null|string>
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
            new EntityExistence(DocumentBaseConfigEntity::class, ['id' => Uuid::randomHex()], true, false, false, []),
            '/0/'
        );

        return new PreWriteValidationEvent(
            WriteContext::createFromContext($this->context),
            [$writeCommand]
        );
    }


}
