<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentBaseConfigValidator::class)]
class DocumentBaseConfigValidatorTest extends TestCase
{
    private DocumentBaseConfigDefinition $definition;

    private ProductDefinition $productDefinition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [DocumentBaseConfigDefinition::class, ProductDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->get(DocumentBaseConfigDefinition::class);
        static::assertInstanceOf(DocumentBaseConfigDefinition::class, $definition);
        $this->definition = $definition;

        $definition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $definition);
        $this->productDefinition = $definition;
    }

    public function testSubscribedEvents(): void
    {
        $events = DocumentBaseConfigValidator::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertSame('preValidate', $events[PreWriteValidationEvent::class]);
    }

    public function testSkipsDifferentEntity(): void
    {
        $event = $this->createEvent([
            new InsertCommand(
                $this->productDefinition,
                ['name' => 'test'],
                ['id' => Uuid::randomBytes()],
                $this->createMock(EntityExistence::class),
                '/0'
            ),
        ]);

        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testSkipsDeleteCommand(): void
    {
        $event = $this->createEvent([
            new DeleteCommand(
                $this->definition,
                ['id' => Uuid::randomBytes()],
                $this->createMock(EntityExistence::class),
            ),
        ]);

        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $expectedViolationPaths
     */
    #[DataProvider('insertValidationProvider')]
    public function testInsertValidation(array $config, array $expectedViolationPaths): void
    {
        $event = $this->createEvent([
            new InsertCommand(
                $this->definition,
                ['config' => json_encode($config, \JSON_THROW_ON_ERROR)],
                ['id' => Uuid::randomBytes()],
                $this->createMock(EntityExistence::class),
                '/0'
            ),
        ]);

        $this->createValidator()->preValidate($event);

        if ($expectedViolationPaths === []) {
            static::assertCount(0, $event->getExceptions()->getExceptions());

            return;
        }

        $this->assertViolationPaths($event, $expectedViolationPaths);
    }

    public static function insertValidationProvider(): \Generator
    {
        $validConfig = [
            'pageSize' => 'a4',
            'pageOrientation' => 'portrait',
            'itemsPerPage' => 10,
            'fileTypes' => ['pdf'],
            'displayCompanyAddress' => false,
            'displayReturnAddress' => false,
        ];

        yield 'valid config - no violations' => [
            'config' => $validConfig,
            'expectedViolationPaths' => [],
        ];

        yield 'empty config - all base fields required' => [
            'config' => [],
            'expectedViolationPaths' => ['/config/pageSize', '/config/pageOrientation', '/config/itemsPerPage', '/config/fileTypes'],
        ];

        yield 'missing pageSize' => [
            'config' => array_diff_key($validConfig, ['pageSize' => true]),
            'expectedViolationPaths' => ['/config/pageSize'],
        ];

        yield 'missing pageOrientation' => [
            'config' => array_diff_key($validConfig, ['pageOrientation' => true]),
            'expectedViolationPaths' => ['/config/pageOrientation'],
        ];

        yield 'missing itemsPerPage' => [
            'config' => array_diff_key($validConfig, ['itemsPerPage' => true]),
            'expectedViolationPaths' => ['/config/itemsPerPage'],
        ];

        yield 'missing fileTypes' => [
            'config' => array_diff_key($validConfig, ['fileTypes' => true]),
            'expectedViolationPaths' => ['/config/fileTypes'],
        ];

        yield 'empty string values are invalid' => [
            'config' => array_merge($validConfig, ['pageSize' => '', 'pageOrientation' => '']),
            'expectedViolationPaths' => ['/config/pageSize', '/config/pageOrientation'],
        ];

        yield 'empty array fileTypes is invalid' => [
            'config' => array_merge($validConfig, ['fileTypes' => []]),
            'expectedViolationPaths' => ['/config/fileTypes'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $expectedViolationPaths
     */
    #[DataProvider('addressValidationProvider')]
    public function testAddressValidation(array $config, array $expectedViolationPaths): void
    {
        $event = $this->createEvent([
            new InsertCommand(
                $this->definition,
                ['config' => json_encode($config, \JSON_THROW_ON_ERROR)],
                ['id' => Uuid::randomBytes()],
                $this->createMock(EntityExistence::class),
                '/0'
            ),
        ]);

        $this->createValidator()->preValidate($event);

        if ($expectedViolationPaths === []) {
            static::assertCount(0, $event->getExceptions()->getExceptions());

            return;
        }

        $this->assertViolationPaths($event, $expectedViolationPaths);
    }

    public static function addressValidationProvider(): \Generator
    {
        $baseConfig = [
            'pageSize' => 'a4',
            'pageOrientation' => 'portrait',
            'itemsPerPage' => 10,
            'fileTypes' => ['pdf'],
        ];

        $validAddress = [
            'companyName' => 'Test GmbH',
            'companyStreet' => 'Main Street 1',
            'companyCountryId' => Uuid::randomHex(),
            'companyZipcode' => '12345',
            'companyCity' => 'Berlin',
        ];

        yield 'displayCompanyAddress true with all address fields - no violations' => [
            'config' => array_merge($baseConfig, $validAddress, ['displayCompanyAddress' => true, 'displayReturnAddress' => false]),
            'expectedViolationPaths' => [],
        ];

        yield 'displayReturnAddress true with all address fields - no violations' => [
            'config' => array_merge($baseConfig, $validAddress, ['displayCompanyAddress' => false, 'displayReturnAddress' => true]),
            'expectedViolationPaths' => [],
        ];

        yield 'both display flags true with all address fields - no violations' => [
            'config' => array_merge($baseConfig, $validAddress, ['displayCompanyAddress' => true, 'displayReturnAddress' => true]),
            'expectedViolationPaths' => [],
        ];

        yield 'displayCompanyAddress true without address fields - all address fields required' => [
            'config' => array_merge($baseConfig, ['displayCompanyAddress' => true, 'displayReturnAddress' => false]),
            'expectedViolationPaths' => ['/config/companyName', '/config/companyStreet', '/config/companyCountryId', '/config/companyZipcode', '/config/companyCity'],
        ];

        yield 'displayReturnAddress true without address fields - all address fields required' => [
            'config' => array_merge($baseConfig, ['displayCompanyAddress' => false, 'displayReturnAddress' => true]),
            'expectedViolationPaths' => ['/config/companyName', '/config/companyStreet', '/config/companyCountryId', '/config/companyZipcode', '/config/companyCity'],
        ];

        yield 'displayCompanyAddress true with partial address - missing fields required' => [
            'config' => array_merge($baseConfig, ['displayCompanyAddress' => true, 'companyName' => 'Test GmbH', 'companyStreet' => 'Main Street 1']),
            'expectedViolationPaths' => ['/config/companyCountryId', '/config/companyZipcode', '/config/companyCity'],
        ];

        yield 'both display flags false - no address validation' => [
            'config' => array_merge($baseConfig, ['displayCompanyAddress' => false, 'displayReturnAddress' => false]),
            'expectedViolationPaths' => [],
        ];

        yield 'display flags not set - no address validation' => [
            'config' => $baseConfig,
            'expectedViolationPaths' => [],
        ];
    }

    /**
     * @param array<string, mixed> $existingConfig
     * @param array<string, mixed> $updateConfig
     * @param list<string> $expectedViolationPaths
     */
    #[DataProvider('updateMergeProvider')]
    public function testUpdateMergesWithExistingConfig(array $existingConfig, array $updateConfig, array $expectedViolationPaths): void
    {
        $id = Uuid::randomBytes();

        $event = $this->createEvent([
            new UpdateCommand(
                $this->definition,
                ['config' => json_encode($updateConfig, \JSON_THROW_ON_ERROR)],
                ['id' => $id],
                EntityExistence::createForEntity(DocumentBaseConfigDefinition::ENTITY_NAME, ['id' => $id]),
                '/0'
            ),
        ]);

        $this->createValidator([['config' => json_encode($existingConfig, \JSON_THROW_ON_ERROR)]])->preValidate($event);

        if ($expectedViolationPaths === []) {
            static::assertCount(0, $event->getExceptions()->getExceptions());

            return;
        }

        $this->assertViolationPaths($event, $expectedViolationPaths);
    }

    public static function updateMergeProvider(): \Generator
    {
        $fullConfig = [
            'pageSize' => 'a4',
            'pageOrientation' => 'portrait',
            'itemsPerPage' => 10,
            'fileTypes' => ['pdf'],
            'displayCompanyAddress' => false,
            'displayReturnAddress' => false,
        ];

        yield 'update single field - existing config fills the rest' => [
            'existingConfig' => $fullConfig,
            'updateConfig' => ['pageSize' => 'a5'],
            'expectedViolationPaths' => [],
        ];

        yield 'update nullifies required field' => [
            'existingConfig' => $fullConfig,
            'updateConfig' => ['pageSize' => null],
            'expectedViolationPaths' => ['/config/pageSize'],
        ];

        yield 'update enables displayCompanyAddress without address fields' => [
            'existingConfig' => $fullConfig,
            'updateConfig' => ['displayCompanyAddress' => true],
            'expectedViolationPaths' => ['/config/companyName', '/config/companyStreet', '/config/companyCountryId', '/config/companyZipcode', '/config/companyCity'],
        ];

        yield 'update enables displayCompanyAddress with address in existing config' => [
            'existingConfig' => array_merge($fullConfig, [
                'companyName' => 'Test GmbH',
                'companyStreet' => 'Main Street 1',
                'companyCountryId' => Uuid::randomHex(),
                'companyZipcode' => '12345',
                'companyCity' => 'Berlin',
            ]),
            'updateConfig' => ['displayCompanyAddress' => true],
            'expectedViolationPaths' => [],
        ];

        yield 'update provides address fields along with enabling displayCompanyAddress' => [
            'existingConfig' => $fullConfig,
            'updateConfig' => [
                'displayCompanyAddress' => true,
                'companyName' => 'Test GmbH',
                'companyStreet' => 'Main Street 1',
                'companyCountryId' => Uuid::randomHex(),
                'companyZipcode' => '12345',
                'companyCity' => 'Berlin',
            ],
            'expectedViolationPaths' => [],
        ];

        yield 'no existing config - update must have all fields' => [
            'existingConfig' => [],
            'updateConfig' => ['pageSize' => 'a4'],
            'expectedViolationPaths' => ['/config/pageOrientation', '/config/itemsPerPage', '/config/fileTypes'],
        ];
    }

    /**
     * @param list<string> $expectedPaths
     */
    private function assertViolationPaths(PreWriteValidationEvent $event, array $expectedPaths): void
    {
        static::expectException(WriteException::class);
        $event->getExceptions()->tryToThrow();

        $violations = $event->getExceptions()->getExceptions();
        static::assertNotEmpty($violations);

        $actualPaths = [];

        foreach ($violations as $violation) {
            static::assertInstanceOf(WriteConstraintViolationException::class, $violation);

            foreach ($violation->getViolations() as $v) {
                $actualPaths[] = $v->getPropertyPath();
            }
        }

        sort($expectedPaths);
        sort($actualPaths);

        static::assertSame($expectedPaths, $actualPaths);
    }

    /**
     * @param list<array<string, mixed>> $dbRows
     */
    private function createValidator(array $dbRows = []): DocumentBaseConfigValidator
    {
        return new DocumentBaseConfigValidator(new FakeConnection($dbRows));
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands
        );
    }
}
