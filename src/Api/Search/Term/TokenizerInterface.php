<?php declare(strict_types=1);

namespace Shopware\Api\Search\Term;

interface TokenizerInterface
{
    public function tokenize(string $string): array;
}
