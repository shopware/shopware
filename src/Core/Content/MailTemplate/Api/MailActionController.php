<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\Mail\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('after-sales')]
class MailActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly MailDataProvider $mailDataProvider,
    ) {
    }

    #[Route(
        path: '/api/_action/mail-template/send',
        name: 'api.action.mail_template.send',
        methods: ['POST'],
        defaults: ['_acl' => ['api_send_email']]
    )]
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
        $data = $post->all();
        $templateData = $data['mailTemplateType']['templateData'] ?? [];
        $template = $data['mailTemplate']['contentHtml'] ?? null;

        if (!\is_string($template)) {
            throw MailTemplateException::invalidMailTemplateContent();
        }

        $this->templateRenderer->enableTestMode();
        $renderedTemplate = $this->templateRenderer->render($template, $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return new JsonResponse($renderedTemplate);
    }

    #[Route(path: '/api/_action/mail-template/build2', name: 'api.action.mail_template.build2', methods: ['POST'])]
    public function build2(RequestDataBag $post, Context $context): JsonResponse
    {
        $templateId = $post->get('mailTemplateId');
        $entities = $post->get('entities', [])->all();

        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        $template = $mailTemplate->getContentHtml();
        if (!\is_string($template)) {
            throw MailTemplateException::invalidMailTemplateContent();
        }

        $templateData = $this->mailDataProvider->getTemplateData($mailTemplate, $entities, $context);

        $this->templateRenderer->enableTestMode();
        $renderedTemplate = $this->templateRenderer->render($template, $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return new JsonResponse($renderedTemplate);
    }

    #[Route(
        path: '/api/_action/mail-template/send2',
        name: 'api.action.mail_template.send2',
        methods: ['POST'],
        defaults: ['_acl' => ['api_send_email']]
    )]
    public function send2(RequestDataBag $post, Context $context): JsonResponse
    {
        /** @var array{id: string} $data */
        $data = $post->all();

        $templateId = $post->get('mailTemplateId');
        $entities = $post->get('entities', [])->all();

        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        $data['contentHtml'] = $mailTemplate->getContentHtml();
        $data['contentPlain'] = $mailTemplate->getContentPlain();
        $data['subject'] = $mailTemplate->getSubject();
        $data['senderName'] = $mailTemplate->getSenderName();

        $templateData = $this->mailDataProvider->getTemplateData($mailTemplate, $entities, $context);

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

        $message = $this->mailService->send($data, $context, $templateData);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }
}
