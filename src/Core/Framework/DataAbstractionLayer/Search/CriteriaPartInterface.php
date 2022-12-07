<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

/**
 * @internal
 */
interface CriteriaPartInterface
{
    /**
     * @return list<string>
     */
    public function getFields(): array;
}
