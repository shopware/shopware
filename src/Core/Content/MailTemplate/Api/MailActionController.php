<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MailActionController extends AbstractController
{
    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    public function __construct(
        MailService $mailService,
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
        $message = $this->mailService->send($post, $context);

        return new JsonResponse(['size' => strlen($message->toString())]);
    }

    /**
     * Validates if an email template can be rendered without sending an email
     *
     * @Route("/api/v{version}/_action/mail-template/validate", name="api.action.mail_template.validate", methods={"POST"})
     *
     * @throws StringTemplateRenderingException
     */
    public function validate(RequestDataBag $post): JsonResponse
    {
        $this->templateRenderer->render($post->get('contentHtml', ''), []);
        $this->templateRenderer->render($post->get('contentPlain', ''), []);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
