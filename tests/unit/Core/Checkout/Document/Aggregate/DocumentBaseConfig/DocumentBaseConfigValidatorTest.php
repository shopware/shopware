<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\AbstractDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DeliveryNoteDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\InvoiceDocumentType;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentBaseConfigValidator::class)]
class DocumentBaseConfigValidatorTest extends TestCase
{
    private const GLOBAL_ZUGFERD_INFIXES = '{"zugferd_embedded_pdf":"_zugferd"}';

    private const PDF = '/0/filenameInfixes/pdf';

    private const ZUGFERD = '/0/filenameInfixes/zugferd_embedded_pdf';

    private Context $context;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [DocumentBaseConfigDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testValidateWithNoConfigKeyShouldBeValid(): void
    {
        $event = $this->createEvent($this->updateCommand([]));

        $this->createValidator()->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithEmptyConfigKeyShouldBeValid(): void
    {
        $event = $this->createEvent($this->updateCommand(['config' => null]));

        $this->createValidator()->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithSimpleDateModifierShouldBeValid(): void
    {
        $event = $this->createEvent($this->updateCommand($this->paymentDueDate('+30 day')));

        $this->createValidator()->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithNullShouldBeValid(): void
    {
        $event = $this->createEvent($this->updateCommand($this->paymentDueDate(null)));

        $this->createValidator()->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithEmptyStringShouldBeValid(): void
    {
        $event = $this->createEvent($this->updateCommand($this->paymentDueDate('')));

        $this->createValidator()->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateWithInvalidValueShouldBeInvalid(): void
    {
        $event = $this->createEvent($this->updateCommand($this->paymentDueDate('anyInvalidValue')));

        $this->createValidator()->validate($event);

        $violations = $this->violations($event);
        static::assertCount(1, $violations);
        static::assertSame(DocumentBaseConfigValidator::INVALID_PAYMENT_DUE_DATE, $violations->get(0)->getCode());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $connectionReturns
     * @param list<string> $expectedPaths
     */
    #[DataProvider('filenameInfixCases')]
    #[TestDox('$_dataName')]
    public function testFilenameInfixes(bool $insert, array $payload, array $connectionReturns, array $expectedPaths): void
    {
        $command = $insert ? $this->insertCommand($payload) : $this->updateCommand($payload);
        $event = $this->createEvent($command);

        $this->createValidator($this->createConnection(...$connectionReturns))->validate($event);

        if ($expectedPaths === []) {
            static::assertCount(0, $event->getExceptions()->getExceptions());

            return;
        }

        static::assertSame($expectedPaths, $this->violationPaths($event));
    }

    public static function filenameInfixCases(): \Generator
    {
        $globalInvoice = ['type_name' => 'invoice', 'global' => 1];
        $channelInvoice = ['type_name' => 'invoice', 'global' => 0];
        $seededGlobal = ['fetchOne' => self::GLOBAL_ZUGFERD_INFIXES];

        yield 'global config: same infix for both PDF formats is rejected' => [
            true, $globalInvoice + self::infixes(['pdf' => '_x', 'zugferd_embedded_pdf' => '_x']), [], [self::PDF, self::ZUGFERD],
        ];
        yield 'global config: distinct infixes are accepted' => [
            true, $globalInvoice + self::infixes(['zugferd_embedded_pdf' => '_zugferd']), [], [],
        ];
        yield 'global config: a missing infix counts as empty' => [
            true, $globalInvoice + self::infixes(['zugferd_embedded_pdf' => '']), [], [self::PDF, self::ZUGFERD],
        ];
        yield 'global config: a null map counts as empty' => [
            true, $globalInvoice + self::infixes(null), [], [self::PDF, self::ZUGFERD],
        ];
        yield 'global config: infixes are compared case-insensitively' => [
            true, $globalInvoice + self::infixes(['pdf' => '_A', 'zugferd_embedded_pdf' => '_a']), [], [self::PDF, self::ZUGFERD],
        ];
        yield 'global config: non-string values count as unconfigured instead of failing' => [
            true, $globalInvoice + ['filename_infixes' => '{"pdf":null,"zugferd_embedded_pdf":["_x"]}'], [], [self::PDF, self::ZUGFERD],
        ];
        yield 'delivery note: equal infixes are accepted because no formats share an extension' => [
            true, ['type_name' => 'delivery_note', 'global' => 1] + self::infixes(['html' => '', 'pdf' => '']), [], [],
        ];
        yield 'unknown document type is skipped' => [
            true, ['type_name' => 'unknown_type', 'global' => 1] + self::infixes(['pdf' => '', 'zugferd_embedded_pdf' => '']), [], [],
        ];
        yield 'sales channel config: the global infixes are merged in' => [
            true, $channelInvoice + self::infixes(['pdf' => '_zugferd']), $seededGlobal, [self::PDF, self::ZUGFERD],
        ];
        yield 'sales channel config: no infixes inherit the global infixes' => [
            true, $channelInvoice + self::infixes(null), $seededGlobal, [],
        ];
        yield 'sales channel config: an empty infix inherits the global infix' => [
            true, $channelInvoice + self::infixes(['zugferd_embedded_pdf' => '']), $seededGlobal, [],
        ];
        yield 'sales channel config: without a global row it is checked on its own' => [
            true, $channelInvoice + self::infixes(['pdf' => '', 'zugferd_embedded_pdf' => '']), ['fetchOne' => false], [self::PDF, self::ZUGFERD],
        ];
        yield 'update: type and scope are read from the row when the payload only carries infixes' => [
            false, self::infixes(['pdf' => '_x', 'zugferd_embedded_pdf' => '_x']), ['fetchAssociative' => $globalInvoice + ['filename_infixes' => null]], [self::PDF, self::ZUGFERD],
        ];
        yield 'update: stored infixes are re-validated when the document type changes' => [
            false, ['type_name' => 'invoice'], ['fetchAssociative' => $channelInvoice + ['filename_infixes' => '{"pdf":"_zugferd"}']] + $seededGlobal, [self::PDF, self::ZUGFERD],
        ];
        yield 'update: neither infixes nor scope changed, nothing is checked' => [
            false, ['name' => 'renamed'], ['fetchAssociative' => $globalInvoice + ['filename_infixes' => '{"pdf":"_x","zugferd_embedded_pdf":"_x"}']], [],
        ];
        yield 'update: an unknown row is skipped' => [
            false, self::infixes(['pdf' => '_x', 'zugferd_embedded_pdf' => '_x']), ['fetchAssociative' => false], [],
        ];
        yield 'global config: an infix colliding with an assigned sales channel override is rejected on the global field only' => [
            true, $globalInvoice + self::infixes(['zugferd_embedded_pdf' => '_x']), ['fetchAllAssociative' => [['name' => 'Storefront invoice', 'filename_infixes' => '{"pdf":"_x"}']]], [self::ZUGFERD],
        ];
        yield 'global config: a clash a sales channel config causes on its own is not blamed on the global write' => [
            true, $globalInvoice + self::infixes(['zugferd_embedded_pdf' => '_zugferd']), ['fetchAllAssociative' => [['name' => 'Broken', 'filename_infixes' => '{"pdf":"_x","zugferd_embedded_pdf":"_x"}']]], [],
        ];
        yield 'global config: an empty sales channel override still inherits the clashing global infix' => [
            true, $globalInvoice + self::infixes(['zugferd_embedded_pdf' => '_x']), ['fetchAllAssociative' => [['name' => 'Storefront invoice', 'filename_infixes' => '{"pdf":"_x","zugferd_embedded_pdf":""}']]], [self::ZUGFERD],
        ];
    }

    public function testViolationNamesTheOtherFormatAndCarriesTheInfix(): void
    {
        $event = $this->createEvent($this->insertCommand(
            ['type_name' => 'invoice', 'global' => 1] + self::infixes(['pdf' => '_x', 'zugferd_embedded_pdf' => '_x']),
        ));

        $this->createValidator()->validate($event);

        $pdf = $this->violations($event)->get(0);
        static::assertSame(DocumentBaseConfigValidator::DUPLICATE_FILENAME_INFIX, $pdf->getCode());
        static::assertSame(self::PDF, $pdf->getPropertyPath());
        static::assertSame('_x', $pdf->getInvalidValue());
        static::assertSame([
            '{{ infix }}' => '_x',
            '{{ format }}' => 'pdf',
            '{{ formats }}' => 'zugferd_embedded_pdf',
            '{{ extension }}' => 'pdf',
        ], $pdf->getParameters());
        static::assertSame(
            'The filename infix "_x" for "pdf" produces the same ".pdf" filename as: zugferd_embedded_pdf.',
            $pdf->getMessage(),
        );
    }

    public function testViolationOnAGlobalWriteNamesEveryAffectedSalesChannelConfig(): void
    {
        $connection = $this->createConnection(fetchAllAssociative: [
            ['name' => 'B2B invoice', 'filename_infixes' => '{"pdf":"_x"}'],
            ['name' => 'Storefront invoice', 'filename_infixes' => '{"pdf":"_X"}'],
        ]);
        $event = $this->createEvent($this->insertCommand(
            ['type_name' => 'invoice', 'global' => 1] + self::infixes(['zugferd_embedded_pdf' => '_x']),
        ));

        $this->createValidator($connection)->validate($event);

        $violations = $this->violations($event);
        static::assertCount(1, $violations);
        static::assertSame('B2B invoice, Storefront invoice', $violations->get(0)->getParameters()['{{ configs }}']);
        static::assertSame(
            'The filename infix "_x" for "zugferd_embedded_pdf" produces the same ".pdf" filename as: pdf in the sales channel configuration: B2B invoice, Storefront invoice.',
            $violations->get(0)->getMessage(),
        );
    }

    public function testViolationListsEveryOtherClashingFormat(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
            new StaticDocumentRenderer(DocumentFormat::ZUGFERD_EMBEDDED_PDF),
            new StaticDocumentRenderer('xrechnung_pdf', fileExtension: 'pdf'),
        ]);
        $event = $this->createEvent($this->insertCommand(
            ['type_name' => ThreePdfFormatsDocumentType::TECHNICAL_NAME, 'global' => 1] + self::infixes(null),
        ));

        $this->createValidator(rendererRegistry: $rendererRegistry, documentTypes: [new ThreePdfFormatsDocumentType()])->validate($event);

        $violations = $this->violations($event);
        static::assertCount(3, $violations);
        static::assertSame('zugferd_embedded_pdf, xrechnung_pdf', $violations->get(0)->getParameters()['{{ formats }}']);
        static::assertSame('pdf, xrechnung_pdf', $violations->get(1)->getParameters()['{{ formats }}']);
    }

    public function testFormatsWithoutARegisteredRendererAreSkipped(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);
        $event = $this->createEvent($this->insertCommand(
            ['type_name' => 'invoice', 'global' => 1] + self::infixes(['pdf' => '', 'zugferd_embedded_pdf' => '']),
        ));

        $this->createValidator(rendererRegistry: $rendererRegistry)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param list<AbstractDocumentType>|null $documentTypes
     */
    private function createValidator(
        ?Connection $connection = null,
        ?DocumentRendererRegistry $rendererRegistry = null,
        ?array $documentTypes = null,
    ): DocumentBaseConfigValidator {
        $rendererRegistry ??= new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
            new StaticDocumentRenderer(DocumentFormat::PDF),
            new StaticDocumentRenderer(DocumentFormat::ZUGFERD_XML),
            new StaticDocumentRenderer(DocumentFormat::ZUGFERD_EMBEDDED_PDF),
        ]);
        $appFeatureStorage = static::createStub(AppFeatureStorage::class);
        $appFeatureStorage->method('forActiveApps')->willReturn([]);

        return new DocumentBaseConfigValidator(
            new MockClock('2026-01-01 12:00:00'),
            $connection ?? $this->createConnection(),
            new DocumentTypeRegistry($documentTypes ?? [new InvoiceDocumentType(), new DeliveryNoteDocumentType()], $appFeatureStorage),
            static fn (): DocumentRendererRegistry => $rendererRegistry,
        );
    }

    /**
     * @param array<string, mixed>|false $fetchAssociative
     * @param list<array<string, mixed>> $fetchAllAssociative
     */
    private function createConnection(
        array|false $fetchAssociative = false,
        mixed $fetchOne = false,
        array $fetchAllAssociative = [],
    ): Connection {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($fetchAssociative);
        $connection->method('fetchOne')->willReturn($fetchOne);
        $connection->method('fetchAllAssociative')->willReturn($fetchAllAssociative);

        return $connection;
    }

    /**
     * @return array<string, string|null>
     */
    private function paymentDueDate(?string $value): array
    {
        return ['config' => \json_encode(['paymentDueDate' => $value], \JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string, string>|null $map
     *
     * @return array<string, string|null>
     */
    private static function infixes(?array $map): array
    {
        return ['filename_infixes' => $map === null ? null : \json_encode($map, \JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertCommand(array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->definitionInstanceRegistry->get(DocumentBaseConfigDefinition::class),
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateCommand(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->definitionInstanceRegistry->get(DocumentBaseConfigDefinition::class),
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    private function createEvent(WriteCommand $command): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext($this->context),
            [$command]
        );
    }

    private function violations(PreWriteValidationEvent $event): ConstraintViolationListInterface
    {
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);

        return $exceptions[0]->getViolations();
    }

    /**
     * @return list<string>
     */
    private function violationPaths(PreWriteValidationEvent $event): array
    {
        $paths = [];
        foreach ($this->violations($event) as $violation) {
            static::assertSame(DocumentBaseConfigValidator::DUPLICATE_FILENAME_INFIX, $violation->getCode());
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class ThreePdfFormatsDocumentType extends AbstractDocumentType
{
    public const TECHNICAL_NAME = 'three_pdf_formats';

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function getSupportedFormats(): array
    {
        return [
            DocumentFormat::PDF->value,
            DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
            'xrechnung_pdf',
        ];
    }
}
