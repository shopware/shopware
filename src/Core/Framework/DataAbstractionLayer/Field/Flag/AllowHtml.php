<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * In case a column is allowed to contain HTML-esque data. Beware of injection possibilities
 *
 * @package core
 */
class AllowHtml extends Flag
{
    /**
     * @var bool
     */
    protected $sanitized;

    public function __construct(bool $sanitized = true)
    {
        $this->sanitized = $sanitized;
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
