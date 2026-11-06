<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * Aggregates every binding-specification violation of one manifest into a single error.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemBindingSpecificationSchemaError implements Error
{
    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "The following content-system binding specifications are invalid:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
