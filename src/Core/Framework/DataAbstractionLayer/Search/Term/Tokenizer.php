<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class Tokenizer implements TokenizerInterface
{
    /**
     * @param string[] $preservedChars
     *
     * @internal
     */
    public function __construct(
        /**
         * @deprecated tag:v6.8.0 - Property `$tokenMinimumLength` will be removed
         */
        private readonly int $tokenMinimumLength,
        private readonly array $preservedChars = ['-', '_', '+', '.', '@']
    ) {
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'tokenMinimumLength', parameterType: '?int', defaultValue: null)]
    public function tokenize(string $string/* , ?int $tokenMinimumLength = null */): array
    {
        if (\func_num_args() === 2) {
            $tokenMinimumLength = func_get_arg(1) ?? AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;
        } else {
            $tokenMinimumLength = $this->tokenMinimumLength;
        }

        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);

        $allowChars = '';

        foreach ($this->preservedChars as $char) {
            $allowChars .= '\\' . $char;
        }

        $string = trim((string) preg_replace(\sprintf("/[^\pL%s0-9]/u", $allowChars), ' ', $string));

        /** @var list<non-falsy-string> $tags */
        $tags = array_filter(explode(' ', $string));

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);

            if ($tag === '' || mb_strlen($tag) < $tokenMinimumLength) {
                continue;
            }

            $filtered[] = $tag;
        }

        if ($filtered === []) {
            return $tags;
        }

        return array_values(array_unique($filtered));
    }
}
