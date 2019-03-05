<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MailTemplateRendererException extends ShopwareHttpException
{
    protected $code = 'MAIL-TEMPLATING-FAILED';

    public function __construct(string $twigMessage)
    {
        $message = 'Failed rendering mail template using Twig: ' . $twigMessage;
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
