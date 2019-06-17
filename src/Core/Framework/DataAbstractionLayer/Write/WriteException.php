<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;

class WriteException extends ShopwareHttpException
{
    private const MESSAGE = 'There are {{ errorCount }} error(s) while writing data.';
    /**
     * @var \Throwable[]
     */
    private $exceptions;

    public function __construct(array $exceptions = [])
    {
        parent::__construct(self::MESSAGE, ['errorCount' => 0]);

        $this->exceptions = $exceptions;
        $this->updateMessage();
    }

    public function add(\Throwable $exception): void
    {
        $this->exceptions[] = $exception;
        $this->updateMessage();
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function tryToThrow(): void
    {
        if (count($this->exceptions)) {
            throw $this;
        }
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->getExceptions() as $innerException) {
            if ($innerException instanceof WriteConstraintViolationException) {
                /** @var ConstraintViolationInterface $violation */
                foreach ($innerException->getViolations() as $violation) {
                    $path = empty($innerException->getPath()) ? $violation->getPropertyPath() : $innerException->getPath();
                    $error = [
                        'code' => $violation->getCode() ?? $innerException->getErrorCode(),
                        'status' => (string) $this->getStatusCode(),
                        'detail' => $violation->getMessage(),
                        'template' => $violation->getMessageTemplate(),
                        'parameters' => $violation->getParameters(),
                        'source' => [
                            'pointer' => $path,
                        ],
                    ];

                    if ($withTrace) {
                        $error['trace'] = $innerException->getTrace();
                    }

                    yield $error;
                }

                continue;
            }

            $error = [
                'code' => $innerException->getErrorCode(),
                'status' => (string) $this->getStatusCode(),
                'detail' => $innerException->getMessage(),
            ];

            if ($innerException instanceof WriteFieldException) {
                $error['source'] = ['pointer' => $innerException->getPath()];
            }

            if ($withTrace) {
                $error['trace'] = $innerException->getTrace();
            }

            yield $error;
        }
    }

    private function updateMessage(): void
    {
        $this->parameters = ['errorCount' => count($this->exceptions)];
        $this->message = $this->parse(self::MESSAGE, $this->parameters);
    }
}
