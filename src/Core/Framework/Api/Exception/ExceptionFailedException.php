<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package core
 */
class ExceptionFailedException extends ShopwareHttpException
{
    private array $fails = [];

    public function __construct(array $failedExpectations)
    {
        parent::__construct('API Expectations failed', []);
        $this->fails = $failedExpectations;
    }

    public function getParameters(): array
    {
        return $this->fails;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_EXPECTATION_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_EXPECTATION_FAILED;
    }
}
