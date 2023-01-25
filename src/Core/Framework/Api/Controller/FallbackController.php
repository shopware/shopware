<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
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
