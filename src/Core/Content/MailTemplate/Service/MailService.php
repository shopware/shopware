<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;
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

    public function __construct(
        DataValidator $dataValidator,
        StringTemplateRenderer $templateRenderer,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        EntityRepositoryInterface $mediaRepository,
        SalesChannelDefinition $salesChannelDefinition,
        EntityRepositoryInterface $salesChannelRepository,
        SystemConfigService $systemConfigService,
        EventDispatcherInterface $eventDispatcher
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
    }

    public function send(array $data, Context $context, array $templateData = []): ?\Swift_Message
    {
        $definition = $this->getValidationDefinition($context);
        $this->dataValidator->validate($data, $definition);

        $recipients = $data['recipients'];
        $salesChannelId = $data['salesChannelId'];

        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('mailHeaderFooter');
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if ($salesChannel === null) {
            throw new SalesChannelNotFoundException($salesChannelId);
        }

        $senderEmail = $this->systemConfigService->get('core.basicInformation.email', $salesChannelId);

        if ($senderEmail === null) {
            // todo Create log entry, but do not throw exception
            return null;
        }

        $contents = $this->buildContents($data, $salesChannel);
        $templateData['salesChannel'] = $salesChannel;
        foreach ($contents as $index => $template) {
            $contents[$index] = $this->templateRenderer->render($template, $templateData);
        }

        $mediaUrls = $this->getMediaUrls($data['mediaIds'], $context);

        $message = $this->messageFactory->createMessage(
            $data['subject'],
            [$senderEmail => $data['senderName']],
            $recipients,
            $contents,
            $mediaUrls
        );

        $this->mailSender->send($message);

        $mailSentEvent = new MailSentEvent($data['subject'], $recipients, $contents, $context);
        $this->eventDispatcher->dispatch($mailSentEvent, MailSentEvent::EVENT_NAME);

        return $message;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param array $data e.g. ['contentHtml' => 'foobar', 'contentPlain' => '<h1>foobar</h1>']
     *
     * @return array e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     */
    public function buildContents(array $data, SalesChannelEntity $salesChannel): array
    {
        $bodies = [
            'text/html' => $data['contentHtml'],
            'text/plain' => $data['contentPlain'],
        ];
        $mailHeaderFooter = $salesChannel->getMailHeaderFooter();
        if ($mailHeaderFooter !== null) {
            return [
                'text/plain' => $mailHeaderFooter->getHeaderPlain() . $bodies['text/plain'] . $mailHeaderFooter->getFooterPlain(),
                'text/html' => $mailHeaderFooter->getHeaderHtml() . $bodies['text/html'] . $mailHeaderFooter->getFooterHtml(),
            ];
        }

        return $bodies;
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

    private function getMediaUrls(array $mediaIds, Context $context): array
    {
        if (empty($mediaIds)) {
            return [];
        }

        $criteria = new Criteria($mediaIds);

        /** @var MediaCollection $media */
        $media = $this->mediaRepository->search($criteria, $context)->getElements();

        $urls = [];
        foreach ($media as $mediaItem) {
            $urls[] = $mediaItem->getUrl();
        }

        return $urls;
    }
}
