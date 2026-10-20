<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStripTagsRule;

class HasNoStripTags
{
    public function toPlainText(string $html): string
    {
        $escaped = htmlspecialchars($html);
        $name = 'strip_tags';

        return $this->strip_tags($escaped) . $name;
    }

    private function strip_tags(string $html): string
    {
        return $html;
    }
}
