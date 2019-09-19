<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseViolationTypeStruct extends Struct
{
    public const LEVEL_VIOLATION = 'violation';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_INFO = 'info';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $level;

    public function getName(): string
    {
        return $this->name;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
