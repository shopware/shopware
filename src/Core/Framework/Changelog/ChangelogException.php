<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ChangelogException extends HttpException
{
    private const INVALID_VERSION = 'CHANGELOG__INVALID_VERSION';

    private const INVALID_CHANGELOG_FILE = 'CHANGELOG__INVALID_CHANGELOG_FILE';

    private const RELEASE_ALREADY_EXISTS = 'CHANGELOG__RELEASE_ALREADY_EXISTS';

    private const VERSION_REQUIRED = 'CHANGELOG__VERSION_REQUIRED';

    private const INVALID_RELEASE_VERSION = 'CHANGELOG__INVALID_RELEASE_VERSION';

    private const TITLE_REQUIRED = 'CHANGELOG__TITLE_REQUIRED';

    private const INVALID_DATE = 'CHANGELOG__INVALID_DATE';

    public static function invalidVersion(string $version): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_VERSION,
            'Unable to generate next version number, supplied version seems invalid ({{ version }})',
            ['version' => $version]
        );
    }

    /**
     * @param list<string> $errors
     */
    public static function invalidChangelogFile(string $path, array $errors): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CHANGELOG_FILE,
            'Invalid file at path: {{ path }}, errors: {{ errors }}',
            ['path' => $path, 'errors' => implode(', ', $errors)]
        );
    }

    public static function releaseAlreadyExists(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RELEASE_ALREADY_EXISTS,
            'A given version release existed already. Please specify another version or use "-f" to override the existing.'
        );
    }

    public static function versionRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VERSION_REQUIRED,
            'Version of release is required.'
        );
    }

    public static function invalidReleaseVersion(string $version): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_RELEASE_VERSION,
            'Invalid version of release ("{{ version }}"). It should be 4-digits type',
            ['version' => $version]
        );
    }

    public static function titleRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TITLE_REQUIRED,
            'Title is required in changelog file'
        );
    }

    public static function invalidDate(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DATE,
            'The date has to follow the format: YYYY-MM-DD'
        );
    }
}
