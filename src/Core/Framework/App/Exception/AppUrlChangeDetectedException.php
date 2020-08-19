<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class AppUrlChangeDetectedException extends \Exception
{
    public function __construct(string $previousUrl, string $currentUrl)
    {
        parent::__construct(sprintf('Detected APP_URL change, was "%s" and is now "%s".', $previousUrl, $currentUrl));
    }
}
