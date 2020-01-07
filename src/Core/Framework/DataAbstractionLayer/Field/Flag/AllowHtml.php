<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * In case a column is allowed to contain HTML-esque data. Beware of injection possibilities
 */
class AllowHtml extends Flag
{
    public function parse(): \Generator
    {
        yield 'allow_html' => true;
    }
}
