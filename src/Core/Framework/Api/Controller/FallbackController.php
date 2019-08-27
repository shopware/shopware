<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @RouteScope(scopes={"default"})
 */
class FallbackController extends AbstractController
{
    public function rootFallback(): Response
    {
        $page = <<<HTML
<html lang="en">
    <head>
        <meta name="robots" content="noindex, nofollow">
    </head>
    <body></body>
</html>
HTML;

        return new Response($page);
    }
}
