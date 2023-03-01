<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

#[Package('checkout')]
abstract class Error extends \Exception implements \JsonSerializable
{
    //allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;

    //allows to assign array data to this object
    use AssignArrayTrait;

    //allows to create a new instance with all data of the provided object
    use CreateFromTrait;

    final public const LEVEL_NOTICE = 0;

    final public const LEVEL_WARNING = 10;

    final public const LEVEL_ERROR = 20;

    abstract public function getId(): string;

    abstract public function getMessageKey(): string;

    abstract public function getLevel(): int;

    abstract public function blockOrder(): bool;

    public function blockResubmit(): bool
    {
        return $this->blockOrder();
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function getParameters(): array;

    public function getRoute(): ?ErrorRoute
    {
        return null;
    }

    /**
     * Persistent Errors are passed between the shopping cart calculation processes and then displayed to the user.
     *
     * Such errors are used when a validation of the shopping cart takes place and a change is made to the shopping cart in the same step. This happens, for example, in the Product Processor.
     * If a product is invalid, an error is placed in the shopping cart and the product is removed.
     * The error therefore occurs only once and must remain persistent until it is displayed to the user.
     *
     * Non-persistent errors, on the other hand, do not make any changes to the shopping cart, so that this error occurs again and again during the calculation until the user has made the changes himself.
     */
    public function isPersistent(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['key'] = $this->getId();
        $data['level'] = $this->getLevel();
        $data['message'] = $this->getMessage();
        $data['messageKey'] = $this->getMessageKey();
        unset($data['file'], $data['line']);

        return $data;
    }
}
