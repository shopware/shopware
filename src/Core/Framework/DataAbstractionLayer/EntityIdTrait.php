<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
trait EntityIdTrait
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
        $this->_uniqueIdentifier = $id;
    }
}
