<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class Tokenizer implements TokenizerInterface
{
    /**
     * @param string[] $preservedChars
     *
     * @internal
     *
     *  @deprecated tag:v6.8.0 - Property `$tokenMinimumLength` will be removed
     */
    public function __construct(
        private readonly int $tokenMinimumLength,
        private readonly array $preservedChars = ['-', '_', '+', '.', '@']
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $tokenMinimumLength will be added
     */
    public function tokenize(string $string/* , ?int $tokenMinimumLength = null */): array
    {
        if (\func_num_args() === 2) {
            $tokenMinimumLength = func_get_arg(1) ?? AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;
        } else {
            $tokenMinimumLength = $this->tokenMinimumLength;
        }

        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = trim(str_replace(['/', '\\'], ' ', $string));
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);

        $allowChars = '';

        foreach ($this->preservedChars as $char) {
            $allowChars .= '\\' . $char;
        }

        $string = trim((string) preg_replace(\sprintf("/[^\pL%s0-9]/u", $allowChars), ' ', $string));

        /** @var list<string> $tags */
        $tags = array_filter(explode(' ', $string));

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (empty($tag) || mb_strlen($tag) < $tokenMinimumLength) {
                continue;
            }

            $filtered[] = $tag;
        }

        if (empty($filtered)) {
            return $tags;
        }

        return array_values(array_unique($filtered));
    }
}
