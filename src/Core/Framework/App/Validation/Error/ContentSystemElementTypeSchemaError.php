<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemElementTypeSchemaError implements Error
{
    private readonly string $message;

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid element type schema in "%s": %s',
            $filename,
            $reason
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
