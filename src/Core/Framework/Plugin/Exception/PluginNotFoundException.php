<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PluginNotFoundException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-NOT-FOUND';

    public function __construct(string $pluginName, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Plugin by name "%s" not found', $pluginName);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
