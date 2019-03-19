<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Content\MailTemplate\Service\MailBuilder;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MailActionController extends AbstractController
{
    /**
     * @var MailSender
     */
    private $mailSender;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var MailBuilder
     */
    private $mailBuilder;

    public function __construct(MailSender $mailSender, MessageFactory $messageFactory, StringTemplateRenderer $templateRenderer, MailBuilder $mailBuilder)
    {
        $this->mailSender = $mailSender;
        $this->messageFactory = $messageFactory;
        $this->templateRenderer = $templateRenderer;
        $this->mailBuilder = $mailBuilder;
    }

    /**
     * @Route("/api/v{version}/_action/mail-template/send", name="api.action.mail_template.send", methods={"POST"})
     *
     * @throws MissingParameterException
     * @throws StringTemplateRenderingException
     * @throws MailTransportFailedException
     */
    public function send(Request $request, Context $context): JsonResponse
    {
        $internalRequest = InternalRequest::createFromHttpRequest($request);
        $recipient = $internalRequest->requirePost('recipient');
        $template = $internalRequest->requirePost('mailTemplate');
        $salesChannelId = $internalRequest->requirePost('salesChannelId');

        $bodies = ['text/html' => $template['contentHtml'], 'text/plain' => $template['contentPlain']];

        $contents = array_map(function (string $template) {
            return $this->templateRenderer->render($template, []);
        }, $this->mailBuilder->buildContents($context, $bodies, $salesChannelId));

        $message = $this->messageFactory->createMessage(
            $template['subject'],
            [$template['senderMail'] => $template['senderName']],
            [$recipient => $recipient],
            $contents
        );

        $this->mailSender->send($message);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/mail-template/validate", name="api.action.mail_template.validate", methods={"POST"})
     *
     * @throws StringTemplateRenderingException
     */
    public function validate(Request $request): JsonResponse
    {
        $template = $request->request->all();

        $this->templateRenderer->render($template['contentHtml'], []);
        $this->templateRenderer->render($template['contentPlain'], []);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
