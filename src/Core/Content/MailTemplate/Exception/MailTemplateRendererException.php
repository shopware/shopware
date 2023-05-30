<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class MailTemplateRendererException extends ShopwareHttpException
{
    public function __construct(string $twigMessage)
    {
        parent::__construct(
            'Failed rendering mail template using Twig: {{ errorMessage }}',
            ['errorMessage' => $twigMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_TEMPLATING_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
