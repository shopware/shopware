<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;

/**
 * @internal only for use by the app-system
 */
class AppValidationException extends \RuntimeException
{
    public function __construct(string $appName, ErrorCollection $errors)
    {
        $message = sprintf(
            "The app \"%s\" is invalid:\n",
            $appName
        );

        foreach ($errors->getElements() as $error) {
            $message .= "\n" . $error->getMessage();
        }

        parent::__construct($message);
    }
}
