<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class VersionCollection extends EntityCollection
{
    /**
     * @var VersionEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionEntity
    {
        return parent::get($id);
    }

    public function current(): VersionEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}
