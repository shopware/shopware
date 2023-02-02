<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StoreLicenseViolationStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var StoreLicenseViolationTypeStruct
     */
    protected $type;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var StoreActionStruct[]
     */
    protected $actions;

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): StoreLicenseViolationTypeStruct
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getApiAlias(): string
    {
        return 'store_license_violation';
    }
}
