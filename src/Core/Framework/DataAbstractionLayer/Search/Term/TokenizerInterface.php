<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface TokenizerInterface
{
    /**
     * @return list<string>
     */
    public function tokenize(string $string): array;
}
