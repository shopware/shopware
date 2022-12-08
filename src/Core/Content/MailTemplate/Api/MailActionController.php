<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Service\AttachmentLoader;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class MailActionController extends AbstractController
{
    private AbstractMailService $mailService;

    private StringTemplateRenderer $templateRenderer;

    private AttachmentLoader $attachmentLoader;

    /**
     * @internal
     */
    public function __construct(
        AbstractMailService $mailService,
        StringTemplateRenderer $templateRenderer,
        AttachmentLoader $attachmentLoader
    ) {
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
        $this->attachmentLoader = $attachmentLoader;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/mail-template/send", name="api.action.mail_template.send", methods={"POST"})
     */
    public function send(RequestDataBag $post, Context $context): JsonResponse
    {
        $data = $post->all();
        $mailTemplateData = $data['mailTemplateData'] ?? [];

        if (!empty($data['documentIds'])) {
            $data['binAttachments'] = \array_merge(
                $data['binAttachments'] ?? [],
                $this->attachmentLoader->load($data['documentIds'], $context)
            );
        }

        $message = $this->mailService->send($data, $context, $mailTemplateData);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/mail-template/validate", name="api.action.mail_template.validate", methods={"POST"})
     */
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        $this->templateRenderer->initialize();
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/mail-template/build", name="api.action.mail_template.build", methods={"POST"})
     */
    public function build(RequestDataBag $post, Context $context): JsonResponse
    {
        $data = $post->all();
        $templateData = $data['mailTemplateType']['templateData'];

        $this->templateRenderer->enableTestMode();
        $contents['text/html'] = $this->templateRenderer->render($data['mailTemplate']['contentHtml'], $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return new JsonResponse($contents['text/html']);
    }
}
