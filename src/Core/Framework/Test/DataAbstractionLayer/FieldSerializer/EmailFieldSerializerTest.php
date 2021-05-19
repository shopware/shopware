<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

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

class EmailFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private EmailFieldSerializer $serializer;

    private EmailField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(EmailFieldSerializer::class);
        $this->field = (new EmailField('email', 'email'))->addFlags(new ApiAware(), new Required());

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

        /** @var WriteConstraintViolationException|null $exception */
        $exception = null;

        try {
            $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'This value should not be blank.');
        static::assertEquals('/email', $exception->getViolations()->get(0)->getPropertyPath());
    }
}
