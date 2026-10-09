<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class PrivateUrlValidator extends SecureUrlValidator
{
    public function isValidTarget(string $url): bool
    {
        return false;
    }
}
