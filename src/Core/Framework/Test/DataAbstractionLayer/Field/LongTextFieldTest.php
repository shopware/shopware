<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class LongTextFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider longTextFieldDataProvider
     *
     * @param string|null $input
     * @param string|null $expected
     * @param Flag[]      $flags
     */
    public function testLongTextFieldSerializer(string $type, $input, $expected, array $flags = []): void
    {
        $serializer = $this->getContainer()->get(LongTextFieldSerializer::class);

        $data = new KeyValuePair('string', $input, false);

        if ($type === 'writeException') {
            $this->expectException(WriteConstraintViolationException::class);

            try {
                $serializer->encode(
                    $this->getLongTextField($flags),
                    $this->getEntityExisting(),
                    $data,
                    $this->getWriteParameterBagMock()
                )->current();
            } catch (WriteConstraintViolationException $e) {
                static::assertSame('/string', $e->getViolations()->get(0)->getPropertyPath());
                /* Unexpected language has to be fixed NEXT-9419 */
                //static::assertSame($expected, $e->getViolations()->get(0)->getMessage());

                throw $e;
            }
        }

        if ($type === 'assertion') {
            static::assertSame(
                $expected,
                $serializer->encode(
                    $this->getLongTextField($flags),
                    $this->getEntityExisting(),
                    $data,
                    $this->getWriteParameterBagMock()
                )->current()
            );
        }
    }

    /**
     * @return array
     *               Structure:
     *               TestType, input, expected, flags
     */
    public function longTextFieldDataProvider()
    {
        return [
            ['writeException', '<test>', 'This value should not be blank.', [new Required()]],
            ['writeException', null, 'This value should not be blank.', [new Required()]],
            ['writeException', '', 'This value should not be blank.', [new Required()]],
            ['writeException', true, 'This value should be of type string.', [new Required()]],
            ['assertion', 'test12-B', 'test12-B', [new Required()]],
            ['assertion', null, null, []],
            ['assertion', '<test>', '<test>', [new Required(), new AllowHtml()]],
            ['assertion', '', null, []],
            ['assertion', '', '', [new AllowEmptyString()]],
            ['assertion', '', '', [new Required(), new AllowEmptyString()]],
        ];
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    private function getEntityExisting(): EntityExistence
    {
        return new EntityExistence(null, [], true, false, false, []);
    }

    /**
     * @param Flag[] $flags
     */
    private function getLongTextField(array $flags = []): LongTextField
    {
        $field = new LongTextField('string', 'string');

        if ($flags) {
            $field->addFlags(...$flags);
        }

        return $field;
    }
}
