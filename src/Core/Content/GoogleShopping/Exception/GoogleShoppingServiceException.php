<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Symfony\Component\HttpFoundation\Response;

class GoogleShoppingServiceException extends GoogleShoppingException
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var array
     */
    private $errors;

    public function __construct(string $message, int $statusCode, array $errors = [])
    {
        $this->errors = $errors;
        parent::__construct($message, $statusCode);
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->errors as $error) {
            $error = [
                'code' => $this->getErrorCode(),
                'message' => $error['message'],
                'status' => $this->getStatusCode(),
                'title' => Response::$statusTexts[$this->getStatusCode()] ?? 'unknown status',
                'detail' => $error,
            ];

            yield $error;
        }
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SERVICE_EXCEPTION';
    }
}
