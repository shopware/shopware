<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Term;

class Tokenizer implements TokenizerInterface
{
    public function tokenize(string $string): array
    {
        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = trim(str_replace(['.', '-', '/', '\\'], ' ', $string));
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);
        $string = trim(preg_replace("/[^\pL_0-9]/u", ' ', $string));

        $tags = array_unique(explode(' ', $string));
        $tags = array_map('trim', $tags);

        $tags = array_filter(
            array_filter($tags, function ($tag) {
                return strlen($tag) >= 2;
            })
        );

        return $tags;
    }
}
