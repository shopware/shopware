<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginChangelogInvalidException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-CHANGELOG-INVALID';

    public function __construct(string $changelogPath, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The changelog with path "%s" is invalid.', $changelogPath);

        parent::__construct($message, $code, $previous);
    }
}
