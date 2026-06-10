<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(StateMachineStateFieldSerializer::class)]
class StateMachineStateFieldSerializerTest extends TestCase
{
    private StateMachineStateFieldSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new StateMachineStateFieldSerializer(
            Validation::createValidator(),
            $this->createMock(DefinitionInstanceRegistry::class)
        );
    }

    public function testEncodeAllowsAnyStateWhenCreatingEntity(): void
    {
        $stateId = Uuid::randomHex();

        $encoded = $this->withScope(Context::USER_SCOPE, fn (WriteParameterBag $parameters) => iterator_to_array($this->serializer->encode(
            $this->createField(),
            new EntityExistence(null, [], false, false, false, []),
            new KeyValuePair('stateId', $stateId, true),
            $parameters
        )));

        static::assertSame(['state_id' => Uuid::fromHexToBytes($stateId)], $encoded);
    }

    public function testEncodeRejectsStateChangeWhenScopeIsNotAllowed(): void
    {
        $this->withScope(Context::USER_SCOPE, function (WriteParameterBag $parameters): void {
            try {
                iterator_to_array($this->serializer->encode(
                    $this->createField(),
                    new EntityExistence(null, [], true, false, false, []),
                    new KeyValuePair('stateId', Uuid::randomHex(), true),
                    $parameters
                ));

                static::fail(WriteConstraintViolationException::class . ' not thrown.');
            } catch (WriteConstraintViolationException $exception) {
                static::assertSame('/stateId', $exception->getViolations()->get(0)->getPropertyPath());
            }
        });
    }

    public function testEncodeAllowsStateChangeWhenScopeIsAllowed(): void
    {
        $stateId = Uuid::randomHex();

        $encoded = $this->withScope(Context::SYSTEM_SCOPE, fn (WriteParameterBag $parameters) => iterator_to_array($this->serializer->encode(
            $this->createField(),
            new EntityExistence(null, [], true, false, false, []),
            new KeyValuePair('stateId', $stateId, true),
            $parameters
        )));

        static::assertSame(['state_id' => Uuid::fromHexToBytes($stateId)], $encoded);
    }

    private function createField(): StateMachineStateField
    {
        return new StateMachineStateField('state_id', 'stateId', OrderStates::STATE_MACHINE);
    }

    /**
     * @template TReturn
     *
     * @param \Closure(WriteParameterBag): TReturn $callback
     *
     * @return TReturn
     */
    private function withScope(string $scope, \Closure $callback): mixed
    {
        $context = Context::createDefaultContext();

        return $context->scope($scope, fn (Context $scopedContext) => $callback(new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext($scopedContext),
            '',
            new WriteCommandQueue()
        )));
    }
}
