<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly MailTemplateService $mailTemplateService,
    ) {
    }

    /**
     * This route is used to send a mail with the provided mail data in the request.
     * It differs from the "getDataAndSend" route in that it does not gather any data for the mail template
     * on its own, but expects all necessary data to be provided in the request.
     */
    #[Route(
        path: '/api/_action/mail-template/send',
        name: 'api.action.mail_template.send',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_send_email']],
        methods: [Request::METHOD_POST]
    )]
    public function send(RequestDataBag $post, Context $context): JsonResponse
    {
        $data = $post->all();

        if (Feature::isActive('v6.8.0.0')) {
            $flowEventClass = $post->get('flowEventClass');
            \assert(new ($flowEventClass) instanceof FlowEventAware);

            $entitiesDataBag = $post->get('entities', new DataBag());
            \assert($entitiesDataBag instanceof DataBag);
            $entities = $entitiesDataBag->all();

            $templateDataDataBag = $post->get('templateData', new DataBag());
            \assert($templateDataDataBag instanceof DataBag);
            $templateData = $templateDataDataBag->all();

            $message = $this->mailTemplateService->getTemplateDataAndSend(
                $data,
                $context,
                $flowEventClass,
                $entities,
                $templateData,
            );
        } else {
            $message = $this->mailTemplateService->send($data, $context, $data['mailTemplateData'] ?? []);
        }

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    #[Route(
        path: '/api/_action/mail-template/validate',
        name: 'api.action.mail_template.validate',
        methods: [Request::METHOD_POST]
    )]
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        $this->templateRenderer->initialize();
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/mail-template/build',
        name: 'api.action.mail_template.build',
        methods: [Request::METHOD_POST]
    )]
    public function build(RequestDataBag $post, Context $context): JsonResponse
    {
        if (Feature::isActive('v6.8.0.0')) {
            $mailTemplateContent = $post->get('mailTemplateContent');
            \assert($mailTemplateContent instanceof DataBag);
            $mailTemplateContent = $mailTemplateContent->all();

            $flowEventClass = $post->get('flowEventClass');
            \assert(new ($flowEventClass) instanceof FlowEventAware);

            $entitiesDataBag = $post->get('entities', new DataBag());
            \assert($entitiesDataBag instanceof DataBag);
            $entities = $entitiesDataBag->all();

            $templateDataDataBag = $post->get('templateData', new DataBag());
            \assert($templateDataDataBag instanceof DataBag);
            $templateData = $templateDataDataBag->all();

            $strict = $post->get('strict', false);
            \assert(\is_bool($strict));

            $renderedTemplate = $this->mailTemplateService->preview(
                $mailTemplateContent,
                $context,
                $strict,
                $flowEventClass,
                $entities,
                $templateData,
            );

            return new JsonResponse($renderedTemplate);
        }

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

    #[Route(
        path: '/api/_action/mail-template/preview',
        name: 'api.action.mail_template.preview',
        methods: [Request::METHOD_POST]
    )]
    public function preview(RequestDataBag $post, Context $context): JsonResponse
    {
        $templateId = $post->getString('mailTemplateId');

        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $templateContent = [
            'subject' => $mailTemplate->getSubject() ?? '',
            'senderName' => $mailTemplate->getSenderName() ?? '',
            'contentHtml' => $mailTemplate->getContentHtml() ?? '',
            'contentPlain' => $mailTemplate->getContentPlain() ?? '',
        ];

        $flowEventClass = $post->get('flowEventClass');
        \assert(new ($flowEventClass) instanceof FlowEventAware);

        $entitiesDataBag = $post->get('entities', new DataBag());
        \assert($entitiesDataBag instanceof DataBag);
        $entities = $entitiesDataBag->all();

        $templateDataDataBag = $post->get('templateData', new DataBag());
        \assert($templateDataDataBag instanceof DataBag);
        $templateData = $templateDataDataBag->all();

        $strict = $post->get('strict', false);
        \assert(\is_bool($strict));

        $renderedTemplate = $this->mailTemplateService->preview(
            $templateContent,
            $context,
            $strict,
            $flowEventClass,
            $entities,
            $templateData
        );

        return new JsonResponse($renderedTemplate);
    }

    /**
     * This route is used to gather the required data for a mail template and send it.
     * It differs from the "send" route in that it gathers the necessary data for the mail template
     * based on the provided mail template ID and entity IDs.
     */
    #[Route(
        path: '/api/_action/mail-template/get-data-and-send',
        name: 'api.action.mail_template.get_data_and_send',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_send_email']],
        methods: [Request::METHOD_POST]
    )]
    public function getDataAndSend(RequestDataBag $post, Context $context): JsonResponse
    {
        $data = $post->all();

        $templateId = $post->get('mailTemplateId');

        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $data['contentHtml'] ??= $mailTemplate->getContentHtml();
        $data['contentPlain'] ??= $mailTemplate->getContentPlain();
        $data['subject'] ??= $mailTemplate->getSubject();
        $data['senderName'] ??= $mailTemplate->getSenderName();

        $flowEventClass = $post->get('flowEventClass');
        \assert(new ($flowEventClass) instanceof FlowEventAware);

        $entitiesDataBag = $post->get('entities', new DataBag());
        \assert($entitiesDataBag instanceof DataBag);
        $entities = $entitiesDataBag->all();

        $templateDataDataBag = $post->get('templateData', new DataBag());
        \assert($templateDataDataBag instanceof DataBag);
        $templateData = $templateDataDataBag->all();

        $message = $this->mailTemplateService->getTemplateDataAndSend($data, $context, $flowEventClass, $entities, $templateData);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    #[Route(
        path: '/api/_action/mail-template/available-variables',
        name: 'api.action.mail_template.available_variables',
        methods: [Request::METHOD_POST]
    )]
    public function availableVariables(RequestDataBag $post, Context $context): JsonResponse
    {
        $fieldPath = $post->get('fieldPath', '');
        \assert(\is_string($fieldPath));

        $flowEventClass = $post->get('flowEventClass');
        \assert(new ($flowEventClass) instanceof FlowEventAware);

        $entitiesDataBag = $post->get('entities', new DataBag());
        \assert($entitiesDataBag instanceof DataBag);
        $entities = $entitiesDataBag->all();

        $templateDataDataBag = $post->get('templateData', new DataBag());
        \assert($templateDataDataBag instanceof DataBag);
        $templateData = $templateDataDataBag->all();

        return new JsonResponse($this->mailTemplateService->availableVariables($fieldPath, $context, $flowEventClass, $entities, $templateData));
    }
}
