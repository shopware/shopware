<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateTimeDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class DateTimeFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private DateTimeFieldSerializer $serializer;

    private DateTimeField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(DateTimeFieldSerializer::class);
        $this->field = new DateTimeField('date', 'date');
        $this->field->addFlags(new ApiAware(), new Required());

        $definition = $this->registerDefinition(DateTimeDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public static function serializerProvider(): array
    {
        return [
            [
                new \DateTime('2020-05-15 00:00:00', new \DateTimeZone('UTC')),
                new \DateTime('2020-05-15 00:00:00', new \DateTimeZone('UTC')),
            ],
            [
                new \DateTime('2099-05-18 00:00:00', new \DateTimeZone('UTC')),
                new \DateTime('2099-05-18 00:00:00', new \DateTimeZone('UTC')),
            ],
            [
                new \DateTime('2020-05-15 22:00:00', new \DateTimeZone('America/New_York')),
                new \DateTime('2020-05-16 02:00:00', new \DateTimeZone('UTC')),
            ],
        ];
    }

    public static function serializerProviderString(): array
    {
        return [
            [
                '2020-05-15T00:00:00+0000',
                '2020-05-15T00:00:00+00:00',
            ],
            [
                '2020-05-15T00:00:00+0200',
                '2020-05-14T22:00:00+00:00',
            ],
            [
                '2020-05-15T22:00:00+0400',
                '2020-05-15T18:00:00+00:00',
            ],
        ];
    }

    /**
     * @dataProvider serializerProvider
     */
    public function testSerializer(\DateTimeInterface $in, \DateTimeInterface $expected): void
    {
        $kvPair = new KeyValuePair('date', $in, true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertEquals($expected, $decoded, 'Output should be ' . print_r($expected, true));
    }

    /**
     * @dataProvider serializerProviderString
     */
    public function testSerializerString(string $in, string $expected): void
    {
        $kvPair = new KeyValuePair('date', $in, true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);
        static::assertNotNull($decoded);

        static::assertEquals($expected, $decoded->format('c'), 'Output should be ' . $expected);
    }

    public function testSerializerValidatesRequiredField(): void
    {
        $kvPair = new KeyValuePair('date', null, true);
        $this->field->removeFlag(Required::class);

        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertNull($decoded);

        $this->field->addFlags(new Required());
        static::expectException(WriteConstraintViolationException::class);
        $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
    }
}
