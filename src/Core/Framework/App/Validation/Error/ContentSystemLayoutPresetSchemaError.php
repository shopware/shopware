<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemLayoutPresetSchemaError extends Error
{
    private const KEY = 'manifest-invalid-layout-preset-schema';

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid layout preset in "%s": %s',
            $filename,
            $reason
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
