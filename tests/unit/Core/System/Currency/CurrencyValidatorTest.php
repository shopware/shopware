<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CurrencyValidator::class)]
class CurrencyValidatorTest extends TestCase
{
    public function testOnlyDefaultCurrencyDeletionIsRejected(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [CurrencyDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $definition = $definitionRegistry->get(CurrencyDefinition::class);
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand($definition, ['id' => Uuid::fromHexToBytes(Defaults::CURRENCY)], $existence),
                new DeleteCommand($definition, ['id' => Uuid::randomBytes()], $existence),
                new InsertCommand($definition, [], ['id' => Uuid::fromHexToBytes(Defaults::CURRENCY)], $existence, '/currency'),
            ],
        );

        (new CurrencyValidator())->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(CurrencyValidator::VIOLATION_DELETE_DEFAULT_CURRENCY, $exception->getViolations()->get(0)->getCode());
    }
}
