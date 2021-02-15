<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\Validator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\Validator\ShippingMethodValidator;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakeConnection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class ShippingMethodValidatorTest extends TestCase
{
    /**
     * @var WriteContext
     */
    private $context;

    /**
     * @var ShippingMethodDefinition
     */
    private $shippingMethodDefinition;

    public function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $this->shippingMethodDefinition = new ShippingMethodDefinition();
    }

    /**
     * @dataProvider shippingMethodTaxProvider
     */
    public function testShippingMethodValidator(?string $taxType, ?string $taxId, bool $success): void
    {
        $commands[] = new InsertCommand(
            $this->shippingMethodDefinition,
            [
                'name' => 'test',
                'tax_type' => $taxType,
                'tax_id' => $taxId,
                'availability_rule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class),
            '/0/'
        );

        $fakeConnection = new FakeConnection([]);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new ShippingMethodValidator($fakeConnection);
        $validator->preValidate($event);

        $exception = null;

        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            $exception = $e;
        }

        if (!$success) {
            static::assertNotNull($exception);
            static::assertEquals(WriteConstraintViolationException::class, \get_class($exception->getExceptions()[0]));
        } else {
            static::assertNull($exception);
        }
    }

    public function shippingMethodTaxProvider(): iterable
    {
        yield 'Test tax type is null' => [null, null, true];
        yield 'Test tax type is invalid' => ['invalid', null, false];
        yield 'Test tax type is auto' => ['auto', null, true];
        yield 'Test tax type is highest' => ['highest', null, true];
        yield 'Test tax type is fixed without tax ID' => ['fixed', null, false];
        yield 'Test tax type is fixed with tax ID' => ['fixed', Uuid::randomHex(), true];
    }
}
