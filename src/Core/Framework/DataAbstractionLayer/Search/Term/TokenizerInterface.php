<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

interface TokenizerInterface
{
    public function tokenize(string $string): array;
}
