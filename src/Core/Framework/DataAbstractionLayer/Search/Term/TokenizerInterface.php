<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

/**
 * @package core
 */
interface TokenizerInterface
{
    /**
     * @return array<string>
     */
    public function tokenize(string $string): array;
}
