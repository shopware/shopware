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

    public function getErrorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * A failure means the app can't work as declared and refuses the installation.
     * A notice means it can, and lets the installation proceed.
     */
    public function getSeverity(): Severity;
}
