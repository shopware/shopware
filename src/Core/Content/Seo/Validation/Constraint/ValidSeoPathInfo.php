<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation\Constraint;

use Shopware\Core\Content\Seo\Validation\SeoUrlWriteValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a SEO path does not contain characters that would break
 * the frontend router (notably the `%` URL-encoding marker, fragment `#`,
 * query separator `?`, backslashes and ASCII control characters).
 *
 * @internal
 *
 * @codeCoverageIgnore The class only exposes error constants and message getters; behavior lives in the validator.
 */
#[Package('inventory')]
class ValidSeoPathInfo extends Constraint
{
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type string.';
    final public const INVALID_CHARACTERS = 'CONTENT__SEO_URL_INVALID_CHARACTERS';

    /**
     * Characters that must never appear unescaped inside a seo path
     * because they break Symfony routing or cause a 400 Bad Request
     * when the URL is resolved by the frontend (e.g. `%` in "seo/url%/1").
     */
    public const DISALLOWED_CHARACTERS_PATTERN = '/[%#?\\\\\x00-\x1F\x7F]/';

    protected const ERROR_NAMES = [
        self::INVALID_CHARACTERS => 'CONTENT__SEO_URL_INVALID_CHARACTERS',
    ];

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
}
