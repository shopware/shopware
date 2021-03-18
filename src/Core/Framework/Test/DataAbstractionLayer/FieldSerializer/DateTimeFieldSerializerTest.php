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

class DateTimeFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var DateTimeFieldSerializer
     */
    private $serializer;

    /**
     * @var DateTimeField
     */
    private $field;

    /**
     * @var EntityExistence
     */
    private $existence;

    /**
     * @var WriteParameterBag
     */
    private $parameters;

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

    public function serializerProvider(): array
    {
        return [
            [
                [
                    new \DateTime('2020-05-15 00:00:00', new \DateTimeZone('UTC')),
                    new \DateTime('2020-05-15 00:00:00', new \DateTimeZone('UTC')),
                ],
            ],
            [
                [
                    new \DateTime('2099-05-18 00:00:00', new \DateTimeZone('UTC')),
                    new \DateTime('2099-05-18 00:00:00', new \DateTimeZone('UTC')),
                ],
            ],
            [
                [
                    new \DateTime('2020-05-15 22:00:00', new \DateTimeZone('EDT')),
                    new \DateTime('2020-05-16 03:00:00', new \DateTimeZone('UTC')),
                ],
            ],
        ];
    }

    public function serializerProviderString(): array
    {
        return [
            [
                [
                    '2020-05-15T00:00:00+0000',
                    '2020-05-15T00:00:00+00:00',
                ],
            ],
            [
                [
                    '2020-05-15T00:00:00+0200',
                    '2020-05-14T22:00:00+00:00',
                ],
            ],
            [
                [
                    '2020-05-15T22:00:00+0400',
                    '2020-05-15T18:00:00+00:00',
                ],
            ],
        ];
    }

    /**
     * @dataProvider serializerProvider
     */
    public function testSerializer($input): void
    {
        $kvPair = new KeyValuePair('date', $input[0], true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertEquals($input[1], $decoded, 'Output should be ' . print_r($input[1], true));
    }

    /**
     * @dataProvider serializerProviderString
     */
    public function testSerializerString($input): void
    {
        $kvPair = new KeyValuePair('date', $input[0], true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertEquals($input[1], $decoded->format('c'), 'Output should be ' . $input[1]);
    }
}
