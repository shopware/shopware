<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class StringTemplateRenderingException extends ShopwareHttpException
{
    protected $code = 'STRING-TEMPLATE-RENDERING-FAILED';

    public function __construct(string $twigMessage)
    {
        $message = 'Failed rendering string template using Twig: ' . $twigMessage;
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
