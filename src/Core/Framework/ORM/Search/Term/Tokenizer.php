<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Term;

class Tokenizer implements TokenizerInterface
{
    public function tokenize(string $string): array
    {
        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = trim(str_replace(['.', '-', '/', '\\'], ' ', $string));
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);
        $string = trim(preg_replace("/[^\pL_0-9]/u", ' ', $string));

        $tags = explode(' ', $string);

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = \trim($tag);

            if (\strlen($tag) < 3) {
                continue;
            }

            $filtered[$tag] = 1;
        }

        if (empty($filtered)) {
            return $tags;
        }

        $tags = array_keys($filtered);

        foreach ($tags as &$tag) {
            $tag = (string) $tag;
        }

        return $tags;
    }
}
