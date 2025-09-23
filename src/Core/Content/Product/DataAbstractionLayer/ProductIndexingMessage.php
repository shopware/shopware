<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ProductIndexingMessage extends EntityIndexingMessage
{
    /**
     * @var array<string, array<string>>
     */
    private array $fieldsChangeSet = [];

    /**
     * @return array<string, array<string>>
     */
    public function getFieldsChangeSet(): array
    {
        return $this->fieldsChangeSet;
    }

    /**
     * @param array<string, array<string>> $fieldsChangeSet
     */
    public function setFieldsChangeSet(array $fieldsChangeSet): void
    {
        $this->fieldsChangeSet = $fieldsChangeSet;
    }
}
