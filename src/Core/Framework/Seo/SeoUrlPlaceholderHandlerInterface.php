<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Seo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface SeoUrlPlaceholderHandlerInterface
{
    public function generate($name, $parameters = []): string;

    public function replacePlaceholder(Request $request, Response $response, ?string $host = null): void;

    public function generateResolved(Request $request, $name, $parameters = [], ?string $host = null): string;
}
