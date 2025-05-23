<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CreatedByFieldSerializer::class)]
class CreatedByFieldSerializerTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ValidatorInterface&MockObject $validator;

    private CreatedByFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->fieldSerializer = new CreatedByFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
        );
    }

    public function testEncode(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $userId = Uuid::randomHex();

        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext($userId),
            '/',
            new WriteCommandQueue(),
        );

        $return = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertSame($userId, Uuid::fromBytesToHex($return));
    }

    public function testEncodeWithInvalidField(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $wrongField = new JsonField('key', 'key');

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(CreatedByField::class, $wrongField));

        $this->fieldSerializer->encode(
            $wrongField,
            $existence,
            $data,
            $parameters
        )->current();
    }

    public function testEncodeWithExistingEntity(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex()),
            '/',
            new WriteCommandQueue(),
        );

        $result = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertNull($result);
    }

    public function testEncodeWithInvalidScope(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);

        $result = 'foo';
        Context::createDefaultContext()->scope('invalid-scope', function (Context $context) use ($data, $existence, &$result): void {
            $result = $this->fieldSerializer->encode(
                new CreatedByField([Context::USER_SCOPE]),
                $existence,
                $data,
                new WriteParameterBag(
                    $this->createMock(EntityDefinition::class),
                    WriteContext::createFromContext($context),
                    '/',
                    new WriteCommandQueue(),
                )
            )->current();
        });

        static::assertNull($result);
    }

    public function testEncodeWithProvidedValue(): void
    {
        $providedUserId = Uuid::randomHex();
        $data = new KeyValuePair('key', $providedUserId, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex()),
            '/',
            new WriteCommandQueue(),
        );

        $return = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertSame($providedUserId, Uuid::fromBytesToHex($return));
    }

    public function testEncodeWithSeparateVersion(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $versionId = Uuid::randomHex();
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex(), $versionId),
            '/',
            new WriteCommandQueue(),
        );

        $result = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertNull($result);
    }

    public function testEncodeWithSalesChannelApiSource(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null, Defaults::LIVE_VERSION, false),
            '/',
            new WriteCommandQueue(),
        );

        $result = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertNull($result);
    }

    public function testEncodeWithNoUserId(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertNull($result);
    }

    private function createWriteContext(?string $userId, string $versionId = Defaults::LIVE_VERSION, bool $useAdminApiSource = true): WriteContext
    {
        if ($useAdminApiSource) {
            $source = new AdminApiSource($userId);
        } else {
            $source = new SalesChannelApiSource(TestDefaults::SALES_CHANNEL);
        }

        $context = Context::createDefaultContext($source)->createWithVersionId($versionId);

        return WriteContext::createFromContext($context);
    }
}
