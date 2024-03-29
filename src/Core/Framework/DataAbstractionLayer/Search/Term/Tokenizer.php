<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Tokenizer implements TokenizerInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly int $tokenMinimumLength)
    {
    }

    public function tokenize(string $string/* , array $preservedChars = ['.', '/', '\\'] */): array
    {
        $preservedChars = ['.', '@', '+', '/', '\\'];

        if (\func_num_args() > 1) {
            $preservedChars = func_get_arg(1);
        }

        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = trim(str_replace($preservedChars, ' ', $string));
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);
        $string = trim((string) preg_replace("/[^\pL\-@+e._0-9]/u", ' ', $string));

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
