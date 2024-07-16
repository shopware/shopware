<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class LongTextFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param Flag[] $flags
     */
    #[DataProvider('exceptionCases')]
    public function testLongTextFieldSerializerThrowsWriteConstraintException(bool|string|null $input, ?string $expected, array $flags = []): void
    {
        $serializer = $this->getContainer()->get(LongTextFieldSerializer::class);

        $name = 'string_' . Uuid::randomHex();
        $data = new KeyValuePair($name, $input, false);

        $this->expectException(WriteConstraintViolationException::class);

        try {
            $serializer->encode(
                $this->getLongTextField($name, $flags),
                EntityExistence::createEmpty(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('/' . $name, $e->getViolations()->get(0)->getPropertyPath());
            /* Unexpected language has to be fixed NEXT-9419 */
            // static::assertSame($expected, $e->getViolations()->get(0)->getMessage());

            throw $e;
        }
    }

    /**
     * @param Flag[] $flags
     */
    #[DataProvider('longTextFieldDataProvider')]
    public function testLongTextFieldSerializerEncodesValue(bool|string|null $input, ?string $expected, array $flags = []): void
    {
        $serializer = $this->getContainer()->get(LongTextFieldSerializer::class);

        $name = 'string_' . Uuid::randomHex();
        $data = new KeyValuePair($name, $input, false);

        static::assertSame(
            $expected,
            $serializer->encode(
                $this->getLongTextField($name, $flags),
                EntityExistence::createEmpty(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    /**
     * @return array<string, array{bool|string|null, ?string, Flag[]}>
     */
    public static function exceptionCases(): array
    {
        return [
            'Required HTML filtered content throws' => ['<test>', 'This value should not be blank.', [new Required()]],
            'Required null content throws' => [null, 'This value should not be blank.', [new Required()]],
            'Required empty content throws' => ['', 'This value should not be blank.', [new Required()]],
            'Wrong type throws' => [true, 'This value should be of type string.', [new Required()]],
            'Required and allow empty throws with null' => [null, 'This value should not be null.', [new Required(), new AllowEmptyString()]],
        ];
    }

    /**
     * @return array<string, array{bool|string|null, ?string, Flag[]}>
     */
    public static function longTextFieldDataProvider(): array
    {
        return [
            'String values are passed through' => ['test12-B', 'test12-B', [new Required()]],
            'Null is allowed without required flag' => [null, null, []],
            'Sanitation can be turned off' => ['<test>', '<test>', [new Required(), new AllowHtml(false)]],
            'Empty string is treated as null without AllowEmpty flag' => ['', null, []],
            'Empty string is passed through with AllowEmptyFlag' => ['', '', [new AllowEmptyString()]],
            'Empty string is allowed with Required and AllowEmpty flags' => ['', '', [new Required(), new AllowEmptyString()]],
            'Html content is sanitized' => ['<script></script>test12-B', 'test12-B', [new Required(), new AllowHtml()]],
        ];
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    /**
     * @param Flag[] $flags
     */
    private function getLongTextField(string $name, array $flags = []): LongTextField
    {
        $field = new LongTextField($name, $name);

        if ($flags) {
            $field->addFlags(new ApiAware(), ...$flags);
        }

        return $field;
    }
}
