<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\PrimaryKeyBag;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(WriteCommandExtractor::class)]
class WriteCommandExtractorTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('writeProtectedFieldsProvider')]
    public function testExceptionForWriteProtectedFields(array $payload, ContextSource $scope, bool $valid): void
    {
        $definition = new class extends EntityDefinition {
            final public const ENTITY_NAME = 'webhook';

            public function getEntityName(): string
            {
                return self::ENTITY_NAME;
            }

            public function getDefaults(): array
            {
                return [
                    'errorCount' => 0,
                ];
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                    (new StringField('name', 'name'))->addFlags(new Required()),
                    (new IntField('error_count', 'errorCount', 0))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
                ]);
            }
        };

        $data = [
            'name' => 'My super webhook',
        ];
        $data = \array_replace($data, $payload);

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $extractor = new WriteCommandExtractor(
            $this->createMock(EntityWriteGateway::class),
            $registry
        );
        $context = Context::createDefaultContext($scope);

        $parameters = new WriteParameterBag(
            $registry->get($definition::class),
            WriteContext::createFromContext($context),
            '',
            new WriteCommandQueue(),
            new PrimaryKeyBag()
        );

        $extractor->extract($data, $parameters);

        if ($valid) {
            static::assertCount(0, $parameters->getContext()->getExceptions()->getExceptions());

            return;
        }

        static::assertCount(1, $parameters->getContext()->getExceptions()->getExceptions());
        $exception = $parameters->getContext()->getExceptions()->getExceptions();
        $exception = \array_shift($exception);

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violations = $exception->getViolations();
        static::assertCount(1, $violations);
        static::assertInstanceOf(ConstraintViolation::class, $violations->get(0));
        static::assertStringContainsString('This field is write-protected. (Got: "user" scope and "system" is required)', (string) $violations->get(0)->getMessage());
    }

    public static function writeProtectedFieldsProvider(): \Generator
    {
        yield 'Test write webhook with system source and valid error count' => [
            ['errorCount' => 10],
            new SystemSource(),
            true,
        ];

        yield 'Test write webhook with user source and valid error count' => [
            ['errorCount' => 10],
            new AdminApiSource(Uuid::randomHex()),
            false,
        ];

        yield 'Test write without error count and user source' => [
            [],
            new AdminApiSource(Uuid::randomHex()),
            true,
        ];
    }
}
