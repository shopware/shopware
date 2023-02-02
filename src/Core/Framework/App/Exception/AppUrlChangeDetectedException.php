<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppUrlChangeDetectedException extends \Exception
{
    private string $previousUrl;

    private string $currentUrl;

    public function __construct(string $previousUrl, string $currentUrl)
    {
        $this->previousUrl = $previousUrl;
        $this->currentUrl = $currentUrl;

        parent::__construct(sprintf('Detected APP_URL change, was "%s" and is now "%s".', $previousUrl, $currentUrl));
    }

    public function getPreviousUrl(): string
    {
        return $this->previousUrl;
    }

    public function getCurrentUrl(): string
    {
        return $this->currentUrl;
    }
}
