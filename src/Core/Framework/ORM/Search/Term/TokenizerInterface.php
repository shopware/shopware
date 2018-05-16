<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Term;

interface TokenizerInterface
{
    public function tokenize(string $string): array;
}
