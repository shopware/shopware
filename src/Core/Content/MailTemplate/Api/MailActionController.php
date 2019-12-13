<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\MailTemplate\Service\MailServiceInterface;
use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MailActionController extends AbstractController
{
    /**
     * @var MailServiceInterface
     */
    private $mailService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    public function __construct(
        MailServiceInterface $mailService,
        StringTemplateRenderer $templateRenderer
    ) {
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * @Route("/api/v{version}/_action/mail-template/send", name="api.action.mail_template.send", methods={"POST"})
     */
    public function send(RequestDataBag $post, Context $context): JsonResponse
    {
        $message = $this->mailService->send($post->all(), $context);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    /**
     * Validates if an email template can be rendered without sending an email
     *
     * @Route("/api/v{version}/_action/mail-template/validate", name="api.action.mail_template.validate", methods={"POST"})
     *
     * @throws StringTemplateRenderingException
     */
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
