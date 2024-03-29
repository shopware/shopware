<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface TokenizerInterface
{
    /**
     * @deprecated tag:v6.7.0 - Parameter $preservedChars will be added
     *
     * @return list<string>
     */
    public function tokenize(string $string /* , array $preservedChars = ['.', '/', '\\'] */): array;
}
