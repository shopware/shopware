<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Content\MailTemplate\Service\MailBuilder;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Twig\Exception\StringTemplateRenderingException;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(
        MailSender $mailSender,
        MessageFactory $messageFactory,
        StringTemplateRenderer $templateRenderer,
        MailBuilder $mailBuilder,
        EntityRepositoryInterface $mediaRepository,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->mailSender = $mailSender;
        $this->messageFactory = $messageFactory;
        $this->templateRenderer = $templateRenderer;
        $this->mailBuilder = $mailBuilder;
        $this->mediaRepository = $mediaRepository;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @Route("/api/v{version}/_action/mail-template/send", name="api.action.mail_template.send", methods={"POST"})
     *
     * @throws MailTransportFailedException
     * @throws MissingRequestParameterException
     */
    public function send(InternalRequest $request, Context $context): JsonResponse
    {
        $recipient = $request->requirePost('recipient');
        $salesChannelId = $request->requirePost('salesChannelId');

        $bodies = [
            'text/html' => $request->requirePost('contentHtml'),
            'text/plain' => $request->requirePost('contentPlain'),
        ];

        $contents = array_map(function (string $template) {
            return $this->templateRenderer->render($template, []);
        }, $this->mailBuilder->buildContents($context, $bodies, $salesChannelId));

        $message = $this->messageFactory->createMessage(
            $request->requirePost('subject'),
            [$request->requirePost('senderMail') => $request->requirePost('senderName')],
            [$recipient => $recipient],
            $contents,
            $this->getMediaUrls($request->requirePost('mediaIds'), $context)
        );

        $this->mailSender->send($message);

        return new JsonResponse(['size' => strlen($message->toString())]);
    }

    /**
     * Validates if an email template can be rendered without sending an email
     *
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

    private function getMediaUrls(array $mediaIds, Context $context): array
    {
        if (empty($mediaIds)) {
            return [];
        }

        $criteria = new Criteria($mediaIds);

        return array_map(function (MediaEntity $mediaEntity) {
            return $this->urlGenerator->getRelativeMediaUrl($mediaEntity);
        }, $this->mediaRepository->search($criteria, $context)->getElements());
    }
}
