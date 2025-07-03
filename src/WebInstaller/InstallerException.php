<?php declare(strict_types=1);

namespace Shopware\WebInstaller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class InstallerException extends \RuntimeException
{
    public static function cannotFindShopwareInstallation(): self
    {
        return new self('Could not find Shopware installation');
    }

    public static function cannotFindComposerLock(): self
    {
        return new self('Could not find composer.lock file');
    }

    public static function cannotFindShopwareInComposerLock(): self
    {
        return new self('Could not find Shopware in composer.lock file');
    }

    public static function shouldNotLaunch(string $first, string $second, HttpClientInterface $last): self
    {
        $message = \sprintf(
            'This installer should not be launched with "%s" and "%s" using the HTTP client "%s".',
            $first,
            $second,
            $last::class
        );

        return new self($message);
    }
}
