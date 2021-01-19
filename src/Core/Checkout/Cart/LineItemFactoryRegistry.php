<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemTypeNotSupportedException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LineItemFactoryRegistry
{
    /**
     * @var LineItemFactoryInterface[]
     */
    private $handlers;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var DataValidationDefinition
     */
    private $validatorDefinition;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(iterable $handlers, DataValidator $validator, EventDispatcherInterface $eventDispatcher)
    {
        $this->handlers = $handlers;
        $this->validator = $validator;
        $this->validatorDefinition = $this->createValidatorDefinition();
        $this->eventDispatcher = $eventDispatcher;
    }

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

    public function update(Cart $cart, array $data, SalesChannelContext $context): void
    {
        $identifier = $data['id'];

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        if (!isset($data['type'])) {
            $data['type'] = $lineItem->getType();
        }

        $this->validate($data);

        $handler = $this->getHandler($data['type']);

        if (isset($data['quantity'])) {
            $lineItem->setQuantity($data['quantity']);

            /* @deprecated tag:v6.4.0 - The LineItemQuantityChangedEvent will be removed in the future, please use the BeforeLineItemQuantityChangedEvent and AfterLineItemQuantityChangedEvent variants of this event going forward */
            $this->eventDispatcher->dispatch(new LineItemQuantityChangedEvent($lineItem, $cart, $context));
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

        throw new LineItemTypeNotSupportedException($type);
    }

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
                    ->add('precision', new Type('int'))
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
