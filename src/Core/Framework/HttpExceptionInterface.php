<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

interface HttpExceptionInterface
{
    public function getHttpException(): \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
}
