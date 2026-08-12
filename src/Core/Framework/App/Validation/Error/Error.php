<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class Error extends \Exception
{
    abstract public function getMessageKey(): string;

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return [];
    }
}
