<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppValidationException extends AppException
{
    /**
     * @param list<Error> $errors
     */
    public function __construct(
        string $appName,
        private readonly array $errors
    ) {
        $message = \sprintf(
            "The app \"%s\" is invalid:\n",
            $appName
        );

        foreach ($errors as $error) {
            $message .= "\n" . $error->getMessage();
        }

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            AppException::VALIDATION_FAILED,
            $message
        );
    }

    /**
     * @return list<Error>
     */
    public function getValidationErrors(): array
    {
        return $this->errors;
    }
}
