<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Tokenizer implements TokenizerInterface
{
    /**
     * @param string[] $preservedChars
     *
     * @internal
     */
    public function __construct(
        private readonly int $tokenMinimumLength,
        private readonly array $preservedChars = ['-', '_', '+', '.', '@']
    ) {
    }

    public function tokenize(string $string): array
    {
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

            if (empty($tag) || mb_strlen($tag) < $this->tokenMinimumLength) {
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
