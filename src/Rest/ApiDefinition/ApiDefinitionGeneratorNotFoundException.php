<?php declare(strict_types=1);

namespace Shopware\Rest\ApiDefinition;

use Throwable;

class ApiDefinitionGeneratorNotFoundException extends \Exception
{
    public function __construct(string $format, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('A definition generator for format "%s" was not found.', $format);

        parent::__construct($message, $code, $previous);
    }
}
