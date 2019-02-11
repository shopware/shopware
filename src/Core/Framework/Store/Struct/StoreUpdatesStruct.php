<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreUpdatesStruct extends Struct
{
    /**
     * @var string
     */
    private $code;
    /**
     * @var string
     */
    private $highestVersion;
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $name;

    public function __construct(string $code, string $highestVersion, int $id, string $name)
    {
        $this->code = $code;
        $this->highestVersion = $highestVersion;
        $this->id = $id;
        $this->name = $name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getHighestVersion(): string
    {
        return $this->highestVersion;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->getCode(),
            'highestVersion' => $this->getHighestVersion(),
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
