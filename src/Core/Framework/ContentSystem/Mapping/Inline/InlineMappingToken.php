<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\Log\Package;

/**
 * One `{{map:path}}` occurrence found in an author's text.
 *
 * `$match` is kept verbatim rather than rebuilt from `$path`, because the fallback for an uncatalogued token is to
 * leave the author's own spelling — whitespace included — exactly as typed. A malformed token is captured too so
 * diagnostics can report it and rendering can remove it.
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
