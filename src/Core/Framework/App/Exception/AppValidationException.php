<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppValidationException extends AppException
{
    public function __construct(
        string $appName,
        private readonly ErrorCollection $errors
    ) {
        $message = \sprintf(
            "The app \"%s\" is invalid:\n",
            $appName
        );

        foreach ($errors->getElements() as $error) {
            $message .= "\n" . $error->getMessage();
        }

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            AppException::VALIDATION_FAILED,
            $message
        );
    }

    public function getValidationErrors(): ErrorCollection
    {
        return $this->errors;
    }
}
