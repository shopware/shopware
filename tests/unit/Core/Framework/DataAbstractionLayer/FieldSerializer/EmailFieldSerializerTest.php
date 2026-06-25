<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(EmailFieldSerializer::class)]
class EmailFieldSerializerTest extends TestCase
{
    private EmailFieldSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EmailFieldSerializer(
            Validation::createValidator(),
            $this->createMock(DefinitionInstanceRegistry::class)
        );
    }

    public function testRequiredValidationThrowsError(): void
    {
        $field = (new EmailField('email', 'email'))->addFlags(new Required());

        try {
            $this->serializer->encode(
                $field,
                EntityExistence::createEmpty(),
                new KeyValuePair('email', null, true),
                $this->createWriteParameterBag()
            )->current();

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/email', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    #[DataProvider('emailProvider')]
    public function testEncodeConvertsInternationalDomainNameToAscii(string $expected, string $input): void
    {
        $encodedEmail = $this->serializer->encode(
            new EmailField('email', 'email'),
            EntityExistence::createEmpty(),
            new KeyValuePair('email', $input, true),
            $this->createWriteParameterBag()
        );

        static::assertSame($expected, $encodedEmail->current());
    }

    public static function emailProvider(): \Generator
    {
        yield 'email with umlauts' => ['test@xn--tst-qla.de', 'test@täst.de'];
        yield 'already encoded IDN email' => ['test@xn--tst-qla.de', 'test@xn--tst-qla.de'];
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
