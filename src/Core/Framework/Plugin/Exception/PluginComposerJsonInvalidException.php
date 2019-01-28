<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PluginComposerJsonInvalidException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-COMPOSER-JSON-INVALID';

    public function __construct(string $path, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf("The plugin has an invalid 'composer.json' file. Errors: \n%s", $path);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
