<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailService
{
    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var MailSender
     */
    private $mailSender;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var SalesChannelDefinition
     */
    private $salesChannelDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DataValidator $dataValidator,
        StringTemplateRenderer $templateRenderer,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        EntityRepositoryInterface $mediaRepository,
        SalesChannelDefinition $salesChannelDefinition,
        EntityRepositoryInterface $salesChannelRepository,
        SystemConfigService $systemConfigService,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->dataValidator = $dataValidator;
        $this->templateRenderer = $templateRenderer;
        $this->messageFactory = $messageFactory;
        $this->mailSender = $mailSender;
        $this->mediaRepository = $mediaRepository;
        $this->salesChannelDefinition = $salesChannelDefinition;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->systemConfigService = $systemConfigService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function send(array $data, Context $context, array $templateData = []): ?\Swift_Message
    {
        $definition = $this->getValidationDefinition($context);
        $this->dataValidator->validate($data, $definition);

        $recipients = $data['recipients'];
        $salesChannelId = $data['salesChannelId'];
        $salesChannel = null;

        if ($salesChannelId !== null && !isset($templateData['salesChannel'])) {
            $criteria = new Criteria([$salesChannelId]);
            $criteria->addAssociation('mailHeaderFooter');
            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

            if ($salesChannel === null) {
                throw new SalesChannelNotFoundException($salesChannelId);
            }

            $templateData['salesChannel'] = $salesChannel;
        }

        $senderEmail = $this->systemConfigService->get('core.basicInformation.email', $salesChannelId);

        $senderEmail = $senderEmail ?? $this->systemConfigService->get('core.mailerSettings.senderAddress');

        if ($senderEmail === null) {
            $this->logger->error('senderMail not configured for salesChannel: ' . $salesChannelId . '. Please check system_config \'core.basicInformation.email\'');

            return null;
        }

        $contents = $this->buildContents($data, $salesChannel);
        foreach ($contents as $index => $template) {
            try {
                if (isset($data['testMode']) && (bool) $data['testMode'] === true) {
                    $this->templateRenderer->enableTestMode();
                }

                $contents[$index] = $this->templateRenderer->render($template, $templateData, $context);
                $data['subject'] = $this->templateRenderer->render($data['subject'], $templateData, $context);
                $data['senderName'] = $this->templateRenderer->render($data['senderName'], $templateData, $context);

                if (isset($data['testMode']) && (bool) $data['testMode'] === true) {
                    $this->templateRenderer->disableTestMode();
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "Could not render Mail-Template with error message:\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code:' . $e->getCode() . "\n"
                    . 'Template source:'
                    . $template . "\n"
                    . "Template data: \n"
                    . json_encode($templateData) . "\n"
                );

                return null;
            }
        }

        $mediaUrls = $this->getMediaUrls($data, $context);

        $binAttachments = $data['binAttachments'] ?? null;

        $message = $this->messageFactory->createMessage(
            $data['subject'],
            [$senderEmail => $data['senderName']],
            $recipients,
            $contents,
            $mediaUrls,
            $binAttachments
        );

        $this->mailSender->send($message);

        $mailSentEvent = new MailSentEvent($data['subject'], $recipients, $contents, $context);
        $this->eventDispatcher->dispatch($mailSentEvent);

        return $message;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param array $data e.g. ['contentHtml' => 'foobar', 'contentPlain' => '<h1>foobar</h1>']
     *
     * @return array e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     */
    public function buildContents(array $data, ?SalesChannelEntity $salesChannel): array
    {
        if ($salesChannel && $mailHeaderFooter = $salesChannel->getMailHeaderFooter()) {
            return [
                'text/plain' => $mailHeaderFooter->getHeaderPlain() . $data['contentPlain'] . $mailHeaderFooter->getFooterPlain(),
                'text/html' => $mailHeaderFooter->getHeaderHtml() . $data['contentHtml'] . $mailHeaderFooter->getFooterHtml(),
            ];
        }

        return [
            'text/html' => $data['contentHtml'],
            'text/plain' => $data['contentPlain'],
        ];
    }

    private function getValidationDefinition(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('recipients', new NotBlank());
        $definition->add('salesChannelId', new EntityExists(['entity' => $this->salesChannelDefinition->getEntityName(), 'context' => $context]));
        $definition->add('contentHtml', new NotBlank());
        $definition->add('contentPlain', new NotBlank());
        $definition->add('subject', new NotBlank());
        $definition->add('senderName', new NotBlank());

        return $definition;
    }

    private function getMediaUrls(array $data, Context $context): array
    {
        if (!isset($data['mediaIds']) || empty($data['mediaIds'])) {
            return [];
        }
        $criteria = new Criteria($data['mediaIds']);
        $media = null;
        $mediaRepository = $this->mediaRepository;
        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($criteria, $mediaRepository, &$media): void {
            /** @var MediaCollection $media */
            $media = $mediaRepository->search($criteria, $context)->getElements();
        });

        $urls = [];
        foreach ($media as $mediaItem) {
            $urls[] = $mediaItem->getUrl();
        }

        return $urls;
    }
}
