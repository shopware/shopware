<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ShopwareHttpException extends HttpException implements ShopwareException
{
    /**
     * @var array
     */
    protected $parameters = [];

    public function __construct(string $message, array $parameters = [], ?\Throwable $e = null)
    {
        $this->parameters = $parameters;
        $message = $this->parse($message, $parameters);

        parent::__construct($this->getStatusCode(), $message, $e);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        yield $this->getCommonErrorData($withTrace);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    protected function getCommonErrorData(bool $withTrace = false): array
    {
        $error = [
            'status' => (string) $this->getStatusCode(),
            'code' => $this->getErrorCode(),
            'title' => Response::$statusTexts[$this->getStatusCode()] ?? 'unknown status',
            'detail' => $this->getMessage(),
            'meta' => [
                'parameters' => $this->getParameters(),
            ],
        ];

        if ($withTrace) {
            $error['trace'] = $this->getTrace();
        }

        return $error;
    }

    protected function parse(string $message, array $parameters = []): string
    {
        $regex = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                continue;
            }

            $key = preg_replace('/[^a-z]/i', '', $key);
            $regex[sprintf('/\{\{(\s+)?(%s)(\s+)?\}\}/', $key)] = $value;
        }

        return preg_replace(array_keys($regex), array_values($regex), $message);
    }
}
