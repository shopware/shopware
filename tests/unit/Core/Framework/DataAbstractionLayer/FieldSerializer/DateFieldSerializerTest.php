<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DateFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class DateFieldSerializerTest extends TestCase
{
    private DateFieldSerializer $serializer;

    private DateField $field;

    protected function setUp(): void
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory($this->createMock(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new DateFieldSerializer(
            $validator,
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        $this->field = (new DateField('date', 'date'))->addFlags(new Required());
    }

    /**
     * @return iterable<string, array{\DateTimeInterface, string}>
     */
    public static function dateProvider(): iterable
    {
        yield 'utc date' => [
            new \DateTimeImmutable('2020-05-15 00:00:00', new \DateTimeZone('UTC')),
            '2020-05-15',
        ];

        yield 'future utc date' => [
            new \DateTimeImmutable('2099-05-18 00:00:00', new \DateTimeZone('UTC')),
            '2099-05-18',
        ];

        yield 'timezone moves date to next utc day' => [
            new \DateTimeImmutable('2020-05-15 22:00:00', new \DateTimeZone('America/New_York')),
            '2020-05-16',
        ];
    }

    #[DataProvider('dateProvider')]
    public function testEncodeDateValuesAsUtcStorageDates(\DateTimeInterface $date, string $expectedStorageDate): void
    {
        $encoded = $this->encode($date);
        $decoded = $this->serializer->decode($this->field, $encoded['date']);

        static::assertSame(['date' => $expectedStorageDate], $encoded);
        static::assertNotNull($decoded);
        static::assertSame($expectedStorageDate, $decoded->format('Y-m-d'));
    }

    public function testEncodeStringDate(): void
    {
        $encoded = $this->encode('2020-05-15T00:00:00+00:00');

        static::assertSame(['date' => '2020-05-15'], $encoded);
    }

    public function testEncodeSymfonyDateArray(): void
    {
        $encoded = $this->encode(['date' => '2020-05-15T12:30:00+00:00']);

        static::assertSame(['date' => '2020-05-15'], $encoded);
    }

    public function testEncodeNullableFieldYieldsNull(): void
    {
        $this->field->removeFlag(Required::class);

        $encoded = $this->encode(null);
        $decoded = $this->serializer->decode($this->field, $encoded['date']);

        static::assertSame(['date' => null], $encoded);
        static::assertNull($decoded);
    }

    public function testRequiredFieldRejectsNull(): void
    {
        $this->expectException(WriteConstraintViolationException::class);

        $this->encode(null);
    }

    /**
     * @return array<string, mixed>
     */
    private function encode(mixed $value): array
    {
        return iterator_to_array($this->serializer->encode(
            $this->field,
            EntityExistence::createEmpty(),
            new KeyValuePair('date', $value, true),
            $this->createWriteParameterBag()
        ));
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
