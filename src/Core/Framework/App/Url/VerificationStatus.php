<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Url;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum VerificationStatus
{
    case PASS;
    case HARD_FAIL;
    case SOFT_FAIL;

    public function label(): string
    {
        return match ($this) {
            self::PASS => 'Pass',
            self::HARD_FAIL => 'Hard Fail',
            self::SOFT_FAIL => 'Soft Fail',
        };
    }
}
