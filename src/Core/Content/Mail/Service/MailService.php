<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Monolog\Logger;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
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
        $this->urlGenerator = $urlGenerator;
    }

    public function getDecorated(): AbstractMailService
    {
        throw new DecorationPatternException(self::class);
    }

    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $event = new MailBeforeValidateEvent($data, $context, $templateData);
        $this->eventDispatcher->dispatch($event);
        $data = $event->getData();
        $templateData = $event->getTemplateData();

        if ($event->isPropagationStopped()) {
            return null;
        }

        $definition = $this->getValidationDefinition($context);
        $this->dataValidator->validate($data, $definition);

        $recipients = $data['recipients'];
        $salesChannelId = $data['salesChannelId'];
        $salesChannel = null;

        if (($salesChannelId !== null && !isset($templateData['salesChannel'])) || $this->isTestMode($data)) {
            $criteria = $this->getSalesChannelDomainCriteria($salesChannelId, $context);

            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

            if ($salesChannel === null) {
                throw new SalesChannelNotFoundException($salesChannelId);
            }

            $templateData['salesChannel'] = $salesChannel;
        } elseif ($this->templateDataContainsSalesChannel($templateData)) {
            $salesChannel = $templateData['salesChannel'];
        }

        $senderEmail = $this->getSender($data, $salesChannelId, $context);

        $contents = $this->buildContents($data, $salesChannel);
        if ($this->isTestMode($data)) {
            $this->templateRenderer->enableTestMode();
            if (!isset($templateData['order']) && !isset($templateData['order']['deepLinkCode']) || $templateData['order']['deepLinkCode'] === '') {
                $templateData['order']['deepLinkCode'] = 'home';
            }
        }

        $template = $data['subject'];

        try {
            $data['subject'] = html_entity_decode($this->templateRenderer->render($template, $templateData, $context));
            $template = $data['senderName'];
            $data['senderName'] = html_entity_decode($this->templateRenderer->render($template, $templateData, $context));
            foreach ($contents as $index => $template) {
                $contents[$index] = $this->templateRenderer->render($template, $templateData, $context);
            }
        } catch (\Throwable $e) {
            $event = new MailErrorEvent(
                $context,
                Logger::ERROR,
                null,
                "Could not render Mail-Template with error message:\n"
                . $e->getMessage() . "\n"
                . 'Error Code:' . $e->getCode() . "\n"
                . 'Template source:'
                . $template . "\n"
                . "Template data: \n"
                . json_encode($templateData) . "\n"
            );
            $this->eventDispatcher->dispatch($event);

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
            $event = new MailErrorEvent(
                $context,
                Logger::ERROR,
                null,
                "message is null:\n"
                . 'Data:'
                . json_encode($data) . "\n"
                . "Template data: \n"
                . json_encode($templateData) . "\n"
            );
            $this->eventDispatcher->dispatch($event);

            return null;
        }

        $event = new MailBeforeSentEvent($data, $mail, $context);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return null;
        }

        $this->mailSender->send($mail);

        $event = new MailSentEvent($data['subject'], $recipients, $contents, $context);
        $this->eventDispatcher->dispatch($event);

        return $mail;
    }

    private function getSender(array $data, ?string $salesChannelId, Context $context): ?string
    {
        $senderEmail = $data['senderEmail'] ?? null;

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->systemConfigService->get('core.basicInformation.email', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->systemConfigService->get('core.mailerSettings.senderAddress', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $event = new MailErrorEvent(
                $context,
                Logger::ERROR,
                null,
                'senderMail not configured for salesChannel: ' . $salesChannelId . '. Please check system_config \'core.basicInformation.email\''
            );
            $this->eventDispatcher->dispatch($event);

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

    private function isTestMode(array $data = []): bool
    {
        return isset($data['testMode']) && (bool) $data['testMode'] === true;
    }

    private function templateDataContainsSalesChannel(array $templateData): bool
    {
        return isset($templateData['salesChannel']) && $templateData['salesChannel'] instanceof SalesChannelEntity;
    }
}
