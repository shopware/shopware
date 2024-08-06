<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\EmailDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class EmailFieldSerializerTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    private EmailFieldSerializer $serializer;

    private EmailField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $emailField = new EmailField('email', 'email');
        $emailField->addFlags(new ApiAware(), new Required());

        $this->serializer = $this->getContainer()->get(EmailFieldSerializer::class);
        $this->field = $emailField;

        $definition = $this->registerDefinition(EmailDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public function testRequiredValidationThrowsError(): void
    {
        $this->field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('email', null, true);

        $exception = null;

        try {
            $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'This value should not be blank.');
        static::assertEquals('/email', $exception->getViolations()->get(0)->getPropertyPath());
    }

    #[DataProvider('getEmailListProvider')]
    public function testEncode(string $asciiMail, string $utf8Mail): void
    {
        $data = new KeyValuePair('email', $utf8Mail, true);

        $encodedEmail = $this->serializer->encode(
            $this->field,
            $this->createMock(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        static::assertSame($asciiMail, $encodedEmail->current());
    }

    public static function getEmailListProvider(): \Generator
    {
        yield 'email with umlauts' => ['test@xn--tst-qla.de', 'test@täst.de'];
        yield 'idn email' => ['test@xn--tst-qla.de', 'test@xn--tst-qla.de'];
    }
}
