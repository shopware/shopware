<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreLicenseViolationStruct extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var StoreLicenseViolationTypeStruct
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $text;

    /**
     * @var StoreActionStruct[]
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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
