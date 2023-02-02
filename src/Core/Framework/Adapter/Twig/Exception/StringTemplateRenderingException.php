<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class StringTemplateRenderingException extends ShopwareHttpException
{
    public function __construct(string $twigMessage)
    {
        parent::__construct(
            'Failed rendering string template using Twig: {{ message }}',
            ['message' => $twigMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STRING_TEMPLATE_RENDERING_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
