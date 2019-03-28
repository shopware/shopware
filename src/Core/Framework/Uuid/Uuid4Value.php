<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Uuid;

use Ramsey\Uuid\Uuid;

class Uuid4Value
{
    /**
     * @var string
     */
    private $uuid;

    private function __construct(string $hexUuid)
    {
        $this->uuid = $hexUuid;
    }

    public static function random(): self
    {
        // TODO@all create our own Uuid object and do not expose the ramsey object - NEXT-251
        return new self(Uuid::uuid4()->getHex());
    }

    public function getHex(): string
    {
        return $this->uuid;
    }

    public function toString(): string
    {
        return Uuid4Converter::fromHexToString($this->uuid);
    }

    public function getBytes(): string
    {
        return Uuid4Converter::fromHexToBytes($this->uuid);
    }
}
