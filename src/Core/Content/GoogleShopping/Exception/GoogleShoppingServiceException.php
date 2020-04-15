<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class GoogleShoppingServiceException extends ShopwareHttpException
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var array
     */
    private $errors;

    /**
     * @var array
     */
    private $detail;

    public function __construct(?\Google_Service_Exception $exception = null)
    {
        $this->detail = json_decode($exception->getMessage(), true)['error'];
        $this->errors = $exception->getErrors();
        $this->statusCode = $exception->getCode();
        parent::__construct($exception->getMessage(), [], $exception);
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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SERVICE_EXCEPTION';
    }
}
