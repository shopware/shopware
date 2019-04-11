<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ApiVersionTransformation
{
    public function getVersion(): int;

    public function getRoute(): string;

    public function transformRequest(Request $request): void;

    public function transformResponse(Response $response): void;
}
