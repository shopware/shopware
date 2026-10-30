<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SensitiveMail;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ScalarValuesStorer::class)]
class ScalarValuesStorerTest extends TestCase
{
    public function testSensitivitySurvivesBufferedFlowSerialization(): void
    {
        $event = static::createStub(SensitiveScalarMailEvent::class);
        $event->method('getValues')->willReturn(['resetUrl' => 'BODY-SECRET', 'sensitive' => false]);
        $event->method('getName')->willReturn('extension.custom-mail');
        $event->method('getContext')->willReturn(Context::createDefaultContext());

        $factory = new FlowFactory([new ScalarValuesStorer()]);
        $buffered = Serialization::assertRoundTrip($factory->createBuffered($event));
        $restored = $factory->restoreBuffered($buffered);

        static::assertTrue($restored->getData('sensitive'));
        static::assertSame('BODY-SECRET', $restored->getData('resetUrl'));
    }

    public function testSensitiveMailDoesNotRequireScalarValuesAware(): void
    {
        $event = static::createStub(SensitiveMailEvent::class);
        $stored = (new ScalarValuesStorer())->store($event, []);

        static::assertSame(['sensitive' => true], $stored[ScalarValuesAware::STORE_VALUES]);
    }

    public function testOrdinaryScalarValuesRemainUnchanged(): void
    {
        $event = static::createStubForIntersectionOfInterfaces([FlowEventAware::class, ScalarValuesAware::class]);
        $event->method('getValues')->willReturn(['orderNumber' => '10001']);

        static::assertSame([
            ScalarValuesAware::STORE_VALUES => ['orderNumber' => '10001'],
        ], (new ScalarValuesStorer())->store($event, []));
    }

    public function testScalarValuesCannotBeStoredTwice(): void
    {
        $event = static::createStubForIntersectionOfInterfaces([FlowEventAware::class, ScalarValuesAware::class]);

        $this->expectExceptionObject(FlowException::scalarValuesAlreadyStored());

        (new ScalarValuesStorer())->store($event, [ScalarValuesAware::STORE_VALUES => ['orderNumber' => '10001']]);
    }

    public function testOrdinaryMailIsNotMarkedSensitive(): void
    {
        $event = static::createStubForIntersectionOfInterfaces([FlowEventAware::class, MailAware::class]);

        static::assertSame([], (new ScalarValuesStorer())->store($event, []));
    }

    public function testAttributeOnNonMailEventIsIgnored(): void
    {
        $event = static::createStub(SensitiveNonMailEvent::class);

        static::assertSame([], (new ScalarValuesStorer())->store($event, []));
    }
}

/**
 * @internal
 */
#[SensitiveMail]
abstract class SensitiveMailEvent implements FlowEventAware, MailAware
{
}

/**
 * @internal
 */
abstract class SensitiveScalarMailEvent extends SensitiveMailEvent implements ScalarValuesAware
{
}

/**
 * @internal
 */
#[SensitiveMail]
abstract class SensitiveNonMailEvent implements FlowEventAware
{
}
