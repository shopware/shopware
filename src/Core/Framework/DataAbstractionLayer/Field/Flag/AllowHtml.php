<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * In case a column is allowed to contain HTML-esque data. Beware of injection possibilities
 */
#[Package('framework')]
class AllowHtml extends Flag
{
    public function __construct(protected bool $sanitized = true)
    {
    }

    public function parse(): \Generator
    {
        yield 'allow_html' => true;
    }

    public function isSanitized(): bool
    {
        return $this->sanitized;
    }
}
