<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('sales-channel')]
class MailActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly StringTemplateRenderer $templateRenderer
    ) {
    }

    #[Route(path: '/api/_action/mail-template/send', name: 'api.action.mail_template.send', methods: ['POST'])]
    public function send(RequestDataBag $post, Context $context): JsonResponse
    {
        /** @var array{id: string} $data */
        $data = $post->all();

        $mailTemplateData = $data['mailTemplateData'] ?? [];
        $extension = new MailSendSubscriberConfig(
            false,
            $data['documentIds'] ?? [],
            $data['mediaIds'] ?? [],
        );

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            new MailTemplateEntity(),
            $extension,
            [],
            $mailTemplateData['order']['id'] ?? null,
        );

        $message = $this->mailService->send($data, $context, $mailTemplateData);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    #[Route(path: '/api/_action/mail-template/validate', name: 'api.action.mail_template.validate', methods: ['POST'])]
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        $this->templateRenderer->initialize();
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/mail-template/build', name: 'api.action.mail_template.build', methods: ['POST'])]
    public function build(RequestDataBag $post, Context $context): JsonResponse
    {
        $contents = [];
        $data = $post->all();
        $templateData = $data['mailTemplateType']['templateData'];

        $this->templateRenderer->enableTestMode();
        $contents['text/html'] = $this->templateRenderer->render($data['mailTemplate']['contentHtml'], $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return new JsonResponse($contents['text/html']);
    }
}
