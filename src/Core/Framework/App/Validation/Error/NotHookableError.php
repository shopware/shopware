<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class NotHookableError extends Error
{
    private const KEY = 'manifest-not-hookable';

    public function __construct(array $violations)
    {
        $this->message = sprintf(
            "The following webhooks are not hookable:\n- %s",
            implode("\n- ", $violations)
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
