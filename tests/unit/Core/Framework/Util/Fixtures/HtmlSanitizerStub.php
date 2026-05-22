<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util\Fixtures;

use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
final class HtmlSanitizerStub extends HtmlSanitizer
{
    public function __construct()
    {
    }

    /**
     * @param array<string, array<string>>|null $options
     */
    public function sanitize(string $text, ?array $options = [], bool $override = false, ?string $field = null): string
    {
        return $text;
    }
}
