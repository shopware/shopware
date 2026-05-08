<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\Subscriber\LocaleValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LocaleValidator::class)]
class LocaleValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [LocaleDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testItValidates(): void
    {
        $validator = new LocaleValidator();

        $localeDefinition = $this->definitionRegistry->get(LocaleDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $localeDefinition,
                    [
                        'id' => Uuid::randomBytes(),
                    ],
                    $this->createMock(EntityExistence::class)
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'name' => 'foobar',
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'code' => 'de-DE',
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'code' => 'foo-BAR',
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $validator->preWriteValidateEvent($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
    }
}
