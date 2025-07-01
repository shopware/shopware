<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ThemeConfigException extends ShopwareHttpException
{
    private const MESSAGE = 'There are {{ errorCount }} error(s) while validating the theme config.';

    /**
     * @var list<\Throwable>
     */
    private array $exceptions = [];

    public function __construct()
    {
        parent::__construct(self::MESSAGE, ['errorCount' => 0]);
    }

    public function getErrorCode(): string
    {
        return 'THEME_CONFIG_EXCEPTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function add(\Throwable $exception): ThemeConfigException
    {
        $this->exceptions[] = $exception;
        $this->updateMessage();

        return $this;
    }

    public function tryToThrow(): void
    {
        if (\count($this->exceptions)) {
            throw $this;
        }
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

    /**
     * @return list<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    private function updateMessage(): void
    {
        $this->message = $this->parse(self::MESSAGE, ['errorCount' => \count($this->exceptions)]);
    }
}
