<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class CustomerIndexingMessage extends EntityIndexingMessage
{
    private array $getIdsWithEmailChange = [];

    public function setIdsWithEmailChange(array $ids): void
    {
        $this->getIdsWithEmailChange = $ids;
    }

    public function getIdsWithEmailChange(): array
    {
        return $this->getIdsWithEmailChange;
    }
}
