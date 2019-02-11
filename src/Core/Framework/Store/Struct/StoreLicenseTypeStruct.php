<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseTypeStruct extends Struct
{
    public const BUY = 'buy';
    public const RENT = 'rent';
    public const TEST = 'test';
    public const FREE = 'free';
    public const SUPPORT = 'support';

    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray()
    {
        return [
            'name' => $this->getName(),
        ];
    }
}
