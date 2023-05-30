<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final tag:v6.6.0
 */
#[Package('checkout')]
class LineItemFactoryRegistry
{
    private readonly DataValidationDefinition $validatorDefinition;

    /**
     * @param LineItemFactoryInterface[]|iterable $handlers
     *
     * @internal
     */
    public function __construct(
        private readonly iterable $handlers,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->validatorDefinition = $this->createValidatorDefinition();
    }

    /**
     * @param array<string|int, mixed> $data
     */
    public function create(array $data, SalesChannelContext $context): LineItem
    {
        if (!isset($data['id'])) {
            $data['id'] = Uuid::randomHex();
        }

        $this->validate($data);

        $handler = $this->getHandler($data['type']);

        $lineItem = $handler->create($data, $context);
        $lineItem->markModified();

        return $lineItem;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    public function update(Cart $cart, array $data, SalesChannelContext $context): void
    {
        $identifier = $data['id'];

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw CartException::lineItemNotFound($identifier);
        }

        $this->updateLineItem($cart, $data, $lineItem, $context);
    }

    /**
     * @param array<string|int, mixed> $data
     */
    public function updateLineItem(Cart $cart, array $data, LineItem $lineItem, SalesChannelContext $context): void
    {
        if (!isset($data['type'])) {
            $data['type'] = $lineItem->getType();
        }

        $this->validate($data);

        $handler = $this->getHandler($data['type']);

        if (isset($data['quantity'])) {
            $lineItem->setQuantity($data['quantity']);

            $this->eventDispatcher->dispatch(new BeforeLineItemQuantityChangedEvent($lineItem, $cart, $context));
        }

        $lineItem->markModified();

        $handler->update($lineItem, $data, $context);
    }

    private function getHandler(string $type): LineItemFactoryInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        throw CartException::lineItemTypeNotSupported($type);
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function validate(array $data): void
    {
        $this->validator->validate($data, $this->validatorDefinition);
    }

    private function createValidatorDefinition(): DataValidationDefinition
    {
        return (new DataValidationDefinition())
            ->add('id', new Type('string'), new Required())
            ->add('type', new Type('string'), new Required())
            ->add('quantity', new Type('int'))
            ->add('payload', new Type('array'))
            ->add('stackable', new Type('bool'))
            ->add('removable', new Type('bool'))
            ->add('label', new Type('string'))
            ->add('referencedId', new Type('string'))
            ->add('coverId', new Type('string'), new EntityExists(['entity' => MediaDefinition::ENTITY_NAME, 'context' => Context::createDefaultContext()]))
            ->addSub(
                'priceDefinition',
                (new DataValidationDefinition())
                    ->add('type', new Type('string'))
                    ->add('price', new Type('numeric'))
                    ->add('percentage', new Type('numeric'))
                    ->add('quantity', new Type('int'))
                    ->add('isCalculated', new Type('bool'))
                    ->add('listPrice', new Type('numeric'))
                    ->addList(
                        'taxRules',
                        (new DataValidationDefinition())
                            ->add('taxRate', new Type('numeric'))
                            ->add('percentage', new Type('numeric'))
                    )
            );
    }
}
