<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('checkout')]
class TaxProviderExceptions extends ShopwareHttpException
{
    final public const ERROR_CODE = 'CHECKOUT__TAX_PROVIDER_EXCEPTION';

    private const DEFAULT_TEMPLATE = 'There was an error while calculating taxes';
    private const MESSAGE_TEMPLATE = 'There were %d errors while fetching taxes from providers: ' . \PHP_EOL . '%s';

    /**
     * @var array<string, \Throwable[]>
     */
    private array $exceptions = [];

    public function __construct()
    {
        parent::__construct(self::DEFAULT_TEMPLATE);
    }

    public function add(string $taxProviderIdentifier, \Throwable $e): void
    {
        if (!\array_key_exists($taxProviderIdentifier, $this->exceptions)) {
            $this->exceptions[$taxProviderIdentifier] = [];
        }

        $this->exceptions[$taxProviderIdentifier][] = $e;
        $this->updateMessage();
    }

    /**
     * @return \Throwable[]
     */
    public function getErrorsForTaxProvider(string $taxProvider): array
    {
        if (!\array_key_exists($taxProvider, $this->exceptions)) {
            return [];
        }

        return $this->exceptions[$taxProvider];
    }

    public function hasExceptions(): bool
    {
        return !empty($this->exceptions);
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }

    private function updateMessage(): void
    {
        $message = '';

        if (!$this->hasExceptions()) {
            return;
        }

        foreach ($this->exceptions as $provider => $exceptions) {
            foreach ($exceptions as $exception) {
                $message .= \sprintf(
                    'Tax provider \'%s\' threw an exception: %s' . \PHP_EOL,
                    $provider,
                    $exception->getMessage()
                );
            }
        }

        $this->message = \sprintf(
            self::MESSAGE_TEMPLATE,
            \count($this->exceptions),
            $message
        );
    }
}
