<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppNameError implements Error
{
    private readonly string $message;

    public function __construct(string $appName)
    {
        $this->message = \sprintf(
            'The technical app name "%s" in the "manifest.xml" and the folder name must be equal.',
            $appName
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
