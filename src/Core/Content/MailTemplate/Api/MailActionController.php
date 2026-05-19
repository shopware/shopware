<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\Resolver\GetDataAndSendRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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
        private readonly MailTemplateSendService $mailTemplateSendService,
        private readonly MailPayloadFactory $mailPayloadFactory,
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
        $mailPayload = $this->mailPayloadFactory->make($post);
        $mailTemplate = null;

        $mailTemplateId = $post->get('mailTemplateId');
        if (\is_string($mailTemplateId) && $mailTemplateId !== '') {
            $mailTemplate = $this->mailTemplateService->loadTemplate($mailTemplateId, $context);
        }

        $mailTemplateData = $post->get('mailTemplateData', []);
        if ($mailTemplateData instanceof DataBag) {
            $mailTemplateData = $mailTemplateData->all();
        }

        if (!\is_array($mailTemplateData)) {
            $mailTemplateData = [];
        }

        $message = $this->mailTemplateSendService->send($mailPayload, $context, $mailTemplateData, $mailTemplate);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use {@see preview} to validate mail template rendering instead.
     */
    #[Route(
        path: '/api/_action/mail-template/validate',
        name: 'api.action.mail_template.validate',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mail_template:update']],
        methods: [Request::METHOD_POST],
    )]
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Route "api.action.mail_template.validate" is deprecated and will be removed in v6.8.0.0. Use "api.action.mail_template.preview" to validate mail template rendering instead.',
        );

        $this->templateRenderer->initialize();
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use {@see preview} instead.
     */
    #[Route(
        path: '/api/_action/mail-template/build',
        name: 'api.action.mail_template.build',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mail_template:update']],
        methods: [Request::METHOD_POST]
    )]
    public function build(RequestDataBag $post, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Route "api.action.mail_template.build" is deprecated and will be removed in v6.8.0.0. Use "api.action.mail_template.preview" instead.',
        );

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

    /**
     * This route is used to render mail template content against simulated data for a given event name.
     * It differs from the "preview" route in that it generates the template data automatically
     * instead of expecting entity IDs and template data in the request.
     */
    #[Route(
        path: '/api/_action/mail-template/simulate',
        name: 'api.action.mail_template.simulate',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mail_template:read']],
        methods: [Request::METHOD_POST]
    )]
    public function simulate(
        #[MapRequestPayload(resolver: SimulateRequestResolver::class)]
        SimulateRequest $simulateRequest,
        Context $context
    ): JsonResponse {
        $renderedTemplate = $this->mailTemplateService->simulate($simulateRequest, $context);

        return new JsonResponse($renderedTemplate);
    }

    /**
     * This route is used to render a persisted mail template with the entity IDs and template data provided in the request.
     * It differs from the "simulate" route in that it uses caller-provided data instead of generating simulated data from an event name.
     */
    #[Route(
        path: '/api/_action/mail-template/preview',
        name: 'api.action.mail_template.preview',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_send_email']],
        methods: [Request::METHOD_POST]
    )]
    public function preview(
        #[MapRequestPayload(resolver: PreviewRequestResolver::class)]
        PreviewRequest $previewRequest,
        Context $context
    ): JsonResponse {
        $renderedTemplate = $this->mailTemplateService->preview($previewRequest, $context);

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
    public function getDataAndSend(
        #[MapRequestPayload(resolver: GetDataAndSendRequestResolver::class)]
        GetDataAndSendRequest $request,
        Context $context
    ): JsonResponse {
        $message = $this->mailTemplateSendService->getTemplateDataAndSend($request, $context);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    /**
     * This route is used to list variables available for a business event and an optional parent variable path.
     */
    #[Route(
        path: '/api/_action/mail-template/available-variables',
        name: 'api.action.mail_template.available_variables',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mail_template:read']],
        methods: [Request::METHOD_POST]
    )]
    public function availableVariables(RequestDataBag $post, Context $context): JsonResponse
    {
        $eventName = $post->get('eventName');
        if (!\is_string($eventName)) {
            throw MailTemplateException::invalidRequestParameterType('eventName', 'string', get_debug_type($eventName));
        }

        $parentVariablePath = $post->get('parentVariablePath', '');
        if (!\is_string($parentVariablePath)) {
            throw MailTemplateException::invalidRequestParameterType('parentVariablePath', 'string', get_debug_type($parentVariablePath));
        }

        return new JsonResponse($this->mailTemplateService->getAvailableVariables($eventName, $context, $parentVariablePath));
    }
}
