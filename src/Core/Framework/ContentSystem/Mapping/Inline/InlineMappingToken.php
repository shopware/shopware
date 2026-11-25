<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\Log\Package;

/**
 * One `{{map:path}}` occurrence found in an author's text.
 *
 * `$match` preserves the author's spelling, including whitespace, for tokens inside markup, placeholder
 * substitution, and the parse response. Its byte length also locates the end of the span. Malformed tokens
 * are captured so diagnostics can report them and rendering can remove them.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class InlineMappingToken
{
    public function __construct(
        public string $path,
        public string $match,
        public int $offset,
        public bool $insideMarkup = false,
        public bool $malformed = false,
    ) {
    }
}
