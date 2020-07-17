<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

abstract class Error extends \Exception implements \JsonSerializable
{
    //allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;

    //allows to assign array data to this object
    use AssignArrayTrait;

    //allows to create a new instance with all data of the provided object
    use CreateFromTrait;

    public const LEVEL_NOTICE = 0;

    public const LEVEL_WARNING = 10;

    public const LEVEL_ERROR = 20;

    abstract public function getId(): string;

    abstract public function getMessageKey(): string;

    abstract public function getLevel(): int;

    abstract public function blockOrder(): bool;

    abstract public function getParameters(): array;

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
