<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

class Tokenizer implements TokenizerInterface
{
    public function tokenize(string $string): array
    {
        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = trim(str_replace(['.', '/', '\\'], ' ', $string));
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);
        $string = trim(preg_replace("/[^\pL\-_0-9]/u", ' ', $string));

        $tags = explode(' ', $string);

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (empty($tag) || mb_strlen($tag) < 3) {
                continue;
            }

            $filtered[] = $tag;
        }

        if (empty($filtered)) {
            return array_filter($tags);
        }

        return array_unique($filtered);
    }
}
