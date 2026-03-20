<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class JsonlParsingException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $lineNumber,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }
}
