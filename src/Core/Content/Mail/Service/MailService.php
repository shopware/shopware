<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailService extends AbstractMailService
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
     * @var AbstractMailFactory
     */
    private $mailFactory;

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

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var AbstractMailSender
     */
    private $mailSender;

    public function __construct(
        DataValidator $dataValidator,
        StringTemplateRenderer $templateRenderer,
        AbstractMailFactory $mailFactory,
        AbstractMailSender $emailSender,
        EntityRepositoryInterface $mediaRepository,
        SalesChannelDefinition $salesChannelDefinition,
        EntityRepositoryInterface $salesChannelRepository,
        SystemConfigService $systemConfigService,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->dataValidator = $dataValidator;
        $this->templateRenderer = $templateRenderer;
        $this->mailFactory = $mailFactory;
        $this->mailSender = $emailSender;
        $this->mediaRepository = $mediaRepository;
        $this->salesChannelDefinition = $salesChannelDefinition;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->systemConfigService = $systemConfigService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
    }

    public function getDecorated(): AbstractMailService
    {
        throw new DecorationPatternException(self::class);
    }

    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $mailBeforeValidateEvent = new MailBeforeValidateEvent($data, $context, $templateData);
        $this->eventDispatcher->dispatch($mailBeforeValidateEvent);
        $data = $mailBeforeValidateEvent->getData();

        if ($mailBeforeValidateEvent->isPropagationStopped()) {
            return null;
        }

        $definition = $this->getValidationDefinition($context);
        $this->dataValidator->validate($data, $definition);

        $recipients = $data['recipients'];
        $salesChannelId = $data['salesChannelId'];
        $salesChannel = null;

        if ($salesChannelId !== null && !isset($templateData['salesChannel'])) {
            $criteria = $this->getSalesChannelDomainCriteria($salesChannelId, $context);

            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

            if ($salesChannel === null) {
                throw new SalesChannelNotFoundException($salesChannelId);
            }

            $templateData['salesChannel'] = $salesChannel;
        } elseif (isset($templateData['salesChannel'])) {
            $salesChannel = $templateData['salesChannel'];
        }

        $senderEmail = $this->getSender($data, $salesChannelId);

        $contents = $this->buildContents($data, $salesChannel);
        if (isset($data['testMode']) && (bool) $data['testMode'] === true) {
            $this->templateRenderer->enableTestMode();
            if (!isset($templateData['order']) && !isset($templateData['order']['deepLinkCode']) || $templateData['order']['deepLinkCode'] === '') {
                $templateData['order']['deepLinkCode'] = 'home';
            }
        }

        $template = $data['subject'];

        try {
            $data['subject'] = $this->templateRenderer->render($template, $templateData, $context);
            $template = $data['senderName'];
            $data['senderName'] = $this->templateRenderer->render($template, $templateData, $context);
            foreach ($contents as $index => $template) {
                $contents[$index] = $this->templateRenderer->render($template, $templateData, $context);
            }
        } catch (\Throwable $e) {
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
        if (isset($data['testMode']) && (bool) $data['testMode'] === true) {
            $this->templateRenderer->disableTestMode();
        }

        $mediaUrls = $this->getMediaUrls($data, $context);

        $binAttachments = $data['binAttachments'] ?? null;

        $mail = $this->mailFactory->create(
            $data['subject'],
            [$senderEmail => $data['senderName']],
            $recipients,
            $contents,
            $mediaUrls,
            $data,
            $binAttachments
        );

        if ($mail->getBody()->toString() === '') {
            $this->logger->error(
                "message is null:\n"
                . 'Data:'
                . json_encode($data) . "\n"
                . "Template data: \n"
                . json_encode($templateData) . "\n"
            );

            return null;
        }

        $mailBeforeSentEvent = new MailBeforeSentEvent($data, $mail, $context);
        $this->eventDispatcher->dispatch($mailBeforeSentEvent);

        if ($mailBeforeSentEvent->isPropagationStopped()) {
            return null;
        }

        $this->mailSender->send($mail);

        $mailSentEvent = new MailSentEvent($data['subject'], $recipients, $contents, $context);
        $this->eventDispatcher->dispatch($mailSentEvent);

        return $mail;
    }

    private function getSender(array $data, ?string $salesChannelId): ?string
    {
        $senderEmail = $data['senderEmail'] ?? null;

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->systemConfigService->get('core.basicInformation.email', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->systemConfigService->get('core.mailerSettings.senderAddress', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $this->logger->error('senderMail not configured for salesChannel: ' . $salesChannelId . '. Please check system_config \'core.basicInformation.email\'');

            return null;
        }

        return $senderEmail;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param array $data e.g. ['contentHtml' => 'foobar', 'contentPlain' => '<h1>foobar</h1>']
     *
     * @return array e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     *
     * @internal
     */
    private function buildContents(array $data, ?SalesChannelEntity $salesChannel): array
    {
        if ($salesChannel && $mailHeaderFooter = $salesChannel->getMailHeaderFooter()) {
            $headerPlain = $mailHeaderFooter->getTranslation('headerPlain') ?? '';
            $footerPlain = $mailHeaderFooter->getTranslation('footerPlain') ?? '';
            $headerHtml = $mailHeaderFooter->getTranslation('headerHtml') ?? '';
            $footerHtml = $mailHeaderFooter->getTranslation('footerHtml') ?? '';

            return [
                'text/plain' => sprintf('%s%s%s', $headerPlain, $data['contentPlain'], $footerPlain),
                'text/html' => sprintf('%s%s%s', $headerHtml, $data['contentHtml'], $footerHtml),
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
        foreach ($media ?? [] as $mediaItem) {
            $urls[] = $this->urlGenerator->getRelativeMediaUrl($mediaItem);
        }

        return $urls;
    }

    private function getSalesChannelDomainCriteria(string $salesChannelId, Context $context): Criteria
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('mailHeaderFooter');
        $criteria->getAssociation('domains')
            ->addFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );

        return $criteria;
    }
}
