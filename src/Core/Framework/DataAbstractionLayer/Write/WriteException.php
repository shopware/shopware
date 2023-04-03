<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class WriteException extends ShopwareHttpException
{
    private const MESSAGE = "There are {{ errorCount }} error(s) while writing data.\n\n{{ messagesString }}";

    /**
     * @var \Throwable[]
     */
    private array $exceptions = [];

    public function __construct()
    {
        parent::__construct(self::MESSAGE, ['errorCount' => 0]);
    }

    public function add(\Throwable $exception): WriteException
    {
        $this->exceptions[] = $exception;
        $this->updateMessage();

        return $this;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * @throws WriteException
     */
    public function tryToThrow(): void
    {
        if (\count($this->exceptions)) {
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
            if ($innerException instanceof ShopwareHttpException) {
                yield from $innerException->getErrors($withTrace);

                continue;
            }

            $errorFactory = new ErrorResponseFactory();
            yield from $errorFactory->getErrorsFromException($innerException, $withTrace);
        }
    }

    private function updateMessage(): void
    {
        $messages = [];

        foreach ($this->getErrors() as $index => $error) {
            $pointer = $error['source']['pointer'] ?? '/';
            \assert(\is_string($pointer));
            \assert(\is_string($error['detail']));
            $messages[] = sprintf('%d. [%s] %s', $index + 1, $pointer, $error['detail']);
        }

        $messagesString = implode(\PHP_EOL, $messages);

        $this->parameters = [
            'errorCount' => \count($this->exceptions),
            'messages' => $messages,
            'messagesString' => $messagesString,
        ];

        $this->message = $this->parse(self::MESSAGE, $this->parameters);
    }
}
