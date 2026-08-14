<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface Error
{
    public function getMessage(): string;

    public function getMessageKey(): string;

    public function getErrorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;
}
