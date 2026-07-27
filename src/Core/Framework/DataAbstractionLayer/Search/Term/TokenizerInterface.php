<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface TokenizerInterface
{
    /**
     * @return list<string>
     */
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'tokenMinimumLength', parameterType: '?int', defaultValue: null)]
    public function tokenize(string $string/* , ?int $tokenMinimumLength = null */): array;
}
