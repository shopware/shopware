<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class IncompatibleAppError extends Error
{
    private const KEY = 'manifest-incompatible-app';

    public function __construct(private readonly string $appName)
    {
        $this->message = \sprintf('App %s is not compatible with this Shopware version', $appName);

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getErrorCode(): string
    {
        return AppException::NOT_COMPATIBLE;
    }

    public function getParameters(): array
    {
        return ['name' => $this->appName];
    }
}
