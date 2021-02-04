<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 */
class ConfigurationError extends Error
{
    private const KEY = 'manifest-invalid-config';

    public function __construct(array $violations)
    {
        $this->message = sprintf(
            "The following custom components are not allowed to be used in app configuration:\n- %s",
            implode("\n- ", $violations)
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
