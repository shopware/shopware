<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStripTagsRule;

class HasStripTags
{
    public function toPlainText(string $html): string
    {
        $first = strip_tags($html);
        $second = \strip_tags($html);

        return $first . $second;
    }
}
