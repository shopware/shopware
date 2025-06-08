<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

#[Package('inventory')]
abstract class Error extends \Exception implements \JsonSerializable
{
    use AssignArrayTrait;
    use CreateFromTrait;
    use JsonSerializableTrait;

    abstract public function getId(): string;

    abstract public function getMessageKey(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function getParameters(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function getErrorMessages(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['key'] = $this->getId();
        $data['message'] = $this->getMessage();
        $data['messageKey'] = $this->getMessageKey();
        $data['errorMessages'] = $this->getErrorMessages();

        return $data;
    }
}
