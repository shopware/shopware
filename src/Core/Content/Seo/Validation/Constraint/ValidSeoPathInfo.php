<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation\Constraint;

use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\Validation\SeoUrlWriteValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a SEO path does not contain characters that would break
 * the frontend router (notably the `%` URL-encoding marker, fragment `#`,
 * query separator `?`, backslashes and ASCII control characters).
 *
 * @internal
 */
#[Package('inventory')]
class ValidSeoPathInfo extends Constraint
{
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type string.';
    final public const INVALID_CHARACTERS = 'CONTENT__SEO_URL_INVALID_CHARACTERS';

    public const DISALLOWED_CHARACTERS_PATTERN = '/[' . self::DISALLOWED_CHARACTERS . ']/';

    protected const ERROR_NAMES = [
        self::INVALID_CHARACTERS => 'CONTENT__SEO_URL_INVALID_CHARACTERS',
    ];

    /**
     * Character class (regex body, without delimiters/quantifier) of the
     * characters that must never appear unescaped inside a seo path because
     * they break Symfony routing or cause a 400 Bad Request when the URL is
     * resolved by the frontend (e.g. `%` in "seo/url%/1").
     */
    private const DISALLOWED_CHARACTERS = '%#?\\\\\x00-\x1F\x7F';

    /**
     * Separator used when sanitising generated paths. Mirrors the default
     * slugify separator so a collapsed run blends into the rest of the slug.
     */
    private const SANITIZE_SEPARATOR = '-';

    protected string $message = 'The SEO path "{{ path }}" contains disallowed characters.';

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
     * Filters disallowed characters out of a generated path instead of
     * rejecting it. Used on the write paths that produce SEO URLs internally
     * (e.g. {@see SeoUrlPersister}), where a hard
     * rejection would abort the whole indexing batch. Each run of disallowed
     * characters is collapsed into a single separator.
     *
     * Note: replacing a whole run (rather than stripping single characters)
     * is intentional — a `%` produced by `rawurlencode` is part of a
     * `%XX` sequence, so dropping only the `%` would leave dangling bytes.
     */
    public static function sanitize(string $path): string
    {
        return (string) \preg_replace(
            '/[' . self::DISALLOWED_CHARACTERS . ']+/',
            self::SANITIZE_SEPARATOR,
            $path
        );
    }
}
