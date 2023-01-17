<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ExpectationFailedException extends ShopwareHttpException
{
    /**
     * @var array<string>
     */
    private array $fails = [];

    /**
     * @param array<string> $failedExpectations
     */
    public function __construct(array $failedExpectations)
    {
        parent::__construct('API Expectations failed', []);
        $this->fails = $failedExpectations;
    }

    /**
     * @return array<string> $failedExpectations
     */
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
