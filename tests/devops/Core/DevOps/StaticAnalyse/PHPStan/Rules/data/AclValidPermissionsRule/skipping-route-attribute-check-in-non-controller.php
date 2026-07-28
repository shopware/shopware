<?php declare(strict_types=1);

use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

class NotController
{
    #[Route(defaults: ['_httpCache' => true, PlatformRequest::ATTRIBUTE_ACL => ['non-existing-permission']])]
    public function index(): void
    {
    }
}
