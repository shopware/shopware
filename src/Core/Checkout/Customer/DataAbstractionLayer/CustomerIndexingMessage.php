<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class CustomerIndexingMessage extends EntityIndexingMessage
{
    /**
     * @var list<string>
     */
    private array $ids = [];

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @param list<string> $ids
     */
    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }
}
