<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation\Constraint;

use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\Validation\SeoUrlWriteValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a SEO path only contains URL-allowed characters. Rejected
 * are only sequences that can never reach or round-trip through the
 * frontend router: a stray `%` that does not form a valid percent-escape
 * (causes a 400 Bad Request), the fragment marker `#` (never sent to the
 * server), backslashes (normalized to `/` by browsers) and ASCII control
 * characters. Valid percent-escapes (`%C3%A9`) and query strings
 * (`path?foo=bar`) are URL-allowed and stay valid — the SEO resolver
 * supports query-string SEO URLs.
 *
 * @internal
 */
#[Package('inventory')]
class ValidSeoPathInfo extends Constraint
{
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type string.';
    final public const INVALID_CHARACTERS = 'CONTENT__SEO_URL_INVALID_CHARACTERS';

    public const DISALLOWED_CHARACTERS_PATTERN = '/' . self::DISALLOWED_SEQUENCES . '/';

    protected const ERROR_NAMES = [
        self::INVALID_CHARACTERS => 'CONTENT__SEO_URL_INVALID_CHARACTERS',
    ];

    /**
     * Regex body (without delimiters/quantifier) matching the sequences that
     * are not URL-allowed inside a seo path: a `%` that is not part of a valid
     * percent-escape (causes a 400 Bad Request, e.g. "seo/url%/1"), the
     * fragment marker `#`, backslashes and ASCII control characters. `?` and
     * valid `%XX` escapes are deliberately not matched.
     */
    private const DISALLOWED_SEQUENCES = '%(?![0-9A-Fa-f]{2})|[#\\\\\x00-\x1F\x7F]';

    /**
     * Separator used when sanitising generated paths. Mirrors the default
     * slugify separator so a collapsed run blends into the rest of the slug.
     */
    private const SANITIZE_SEPARATOR = '-';

    protected string $message = 'The SEO path "{{ path }}" contains characters that are not allowed in URLs.';

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Single source of truth for the allowed-character check. Reused by:
     *  - {@see ValidSeoPathInfoValidator} (admin form / SEO action controller via `SeoUrlValidationFactory`)
     *  - {@see SeoUrlWriteValidator} (DAL `PreWriteValidationEvent`)
     * so the rules stay in one place regardless of which write path is used.
     */
    public static function containsDisallowedCharacters(string $path): bool
    {
        return \preg_match(self::DISALLOWED_CHARACTERS_PATTERN, $path) === 1;
    }

    /**
     * Filters disallowed sequences out of a generated path instead of
     * rejecting it. Used on the write paths that produce SEO URLs internally
     * (e.g. {@see SeoUrlPersister}), where a hard
     * rejection would abort the whole indexing batch. Each run of disallowed
     * sequences is collapsed into a single separator.
     *
     * Raw spaces are percent-encoded to `%20` first: a stored literal space
     * could never be matched by the resolver because the frontend always
     * sends the space percent-encoded, so a query-bearing SEO path such as
     * `product?colo=red blue` is normalised to `product?colo=red%20blue`.
     *
     * Valid percent-escapes survive untouched, so `rawurlencode(slugify(...))`
     * output for non-ASCII slug configs (e.g. `caf%C3%A9`) is preserved.
     */
    public static function sanitize(string $path): string
    {
        $path = \str_replace(' ', '%20', $path);

        return (string) \preg_replace(
            '/(?:' . self::DISALLOWED_SEQUENCES . ')+/',
            self::SANITIZE_SEPARATOR,
            $path
        );
    }
}
