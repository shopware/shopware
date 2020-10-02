<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class StringFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider stringFieldDataProvider
     *
     * @param string|null $input
     * @param string|null $expected
     */
    public function testStringFieldSerializer(string $type, $input, $expected, bool $required = true, bool $htmlAllowed = false): void
    {
        $serializer = $this->getContainer()->get(StringFieldSerializer::class);

        $data = new KeyValuePair('string', $input, false);

        if ($type === 'writeException') {
            $this->expectException(WriteConstraintViolationException::class);

            try {
                $serializer->encode(
                    $this->getStringField(),
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
                    $this->getStringField($required, $htmlAllowed),
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
     *               TestType, input, expected, fieldRequired, htmlAllowed
     */
    public function stringFieldDataProvider()
    {
        return [
            ['writeException', '<test>', 'This value should not be blank.'],
            ['writeException', null, 'This value should not be blank.'],
            ['writeException', true, 'This value should be of type string.'],
            ['assertion', 'test12-B', 'test12-B'],
            ['assertion', null, null, false],
            ['assertion', '<test>', '<test>', true, true],
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

    private function getStringField(bool $required = true, bool $htmlAllowed = false): StringField
    {
        $field = new StringField('string', 'string');

        if ($htmlAllowed) {
            $field->addFlags(new AllowHtml());
        }

        return $required ? $field->addFlags(new Required()) : $field;
    }
}
