<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class InvalidAppFlowActionVariableException extends \Exception
{
    public function __construct(
        string $appFlowActionId,
        string $param,
        string $message = '',
        int $code = 0
    ) {
        $message = "Could not render template with error message:\n"
            . $message . "\n"
            . 'Template source:'
            . $param . "\n"
            . 'App flow action ID: '
            . $appFlowActionId . "\n";

        parent::__construct($message, $code);
    }
}
