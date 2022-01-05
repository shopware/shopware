<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Api;

use Symfony\Component\HttpFoundation\Response;

class ScriptResponse
{
    public int $code = Response::HTTP_OK;

    public array $body = [];
}
