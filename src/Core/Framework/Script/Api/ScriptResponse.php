<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Script\Facade\ArrayFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptResponse
{
    public int $code = Response::HTTP_OK;

    public ArrayFacade $body;

    public function __construct()
    {
        $this->body = new ArrayFacade([]);
    }
}
