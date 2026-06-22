<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(RemoteAddressFieldSerializer::class)]
class RemoteAddressFieldSerializerTest extends TestCase
{
    private RemoteAddressFieldSerializer $serializer;

    private MockObject&SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->configService = $this->createMock(SystemConfigService::class);
        $this->serializer = new RemoteAddressFieldSerializer(
            Validation::createValidator(),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->configService
        );
    }

    public function testEncodeRejectsInvalidField(): void
    {
        $field = new IntField('remote_address', 'remoteAddress');

        static::expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(RemoteAddressField::class, $field));

        iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', null, false),
            $this->createWriteParameterBag()
        ));
    }

    public function testEncodeAnonymizesAddressByDefault(): void
    {
        $this->configService->method('get')->willReturn(false);

        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', '127.0.0.1', false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['remote_address' => IpUtils::anonymize('127.0.0.1')], $encoded);
    }

    public function testEncodeKeepsAddressWhenConfigured(): void
    {
        $this->configService->method('get')->willReturn(true);

        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', '127.0.0.1', false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['remote_address' => '127.0.0.1'], $encoded);
    }

    public function testEncodeSkipsEmptyAddress(): void
    {
        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', null, false),
            $this->createWriteParameterBag()
        ));

        static::assertSame([], $encoded);
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
