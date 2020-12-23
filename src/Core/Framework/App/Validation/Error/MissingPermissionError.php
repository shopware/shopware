<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 */
class MissingPermissionError extends Error
{
    private const KEY = 'manifest-missing-permission';

    public function __construct(array $violations)
    {
        $this->message = sprintf(
            "The following permissions are missing:\n- %s",
            implode("\n- ", $violations)
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
