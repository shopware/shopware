<?php declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

class NotController
{
    #[Route(defaults: ['_httpCache' => true, '_acl' => ['non-existing-permission']])]
    public function index(): void
    {
    }
}
