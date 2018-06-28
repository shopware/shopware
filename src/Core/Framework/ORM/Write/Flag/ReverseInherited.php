<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Flag;

class ReverseInherited extends Flag
{
    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
