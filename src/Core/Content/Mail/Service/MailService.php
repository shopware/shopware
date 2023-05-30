<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Monolog\Level;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('system-settings')]
class MailService extends AbstractMailService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly AbstractMailFactory $mailFactory,
        private readonly AbstractMailSender $mailSender,
        private readonly EntityRepository $mediaRepository,
        private readonly SalesChannelDefinition $salesChannelDefinition,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractMailService
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $templateData
     */
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

        $senderEmail = $data['senderMail'] ?? $this->getSender($data, $salesChannelId);

        if ($senderEmail === null) {
            $event = new MailErrorEvent(
                $context,
                Level::Error,
                null,
                'senderMail not configured for salesChannel: ' . $salesChannelId . '. Please check system_config \'core.basicInformation.email\'',
                null,
                $templateData
            );

            $this->eventDispatcher->dispatch($event);
            $this->logger->error(
                'senderMail not configured for salesChannel: ' . $salesChannelId . '. Please check system_config \'core.basicInformation.email\'',
                $templateData
            );
        }

        $contents = $this->buildContents($data, $salesChannel);
        if ($this->isTestMode($data)) {
            $this->templateRenderer->enableTestMode();
            if (\is_array($templateData['order'] ?? []) && empty($templateData['order']['deepLinkCode'])) {
                $templateData['order']['deepLinkCode'] = 'home';
            }
        }
        $template = $data['subject'];

        try {
            $data['subject'] = $this->templateRenderer->render($template, $templateData, $context, false);
            $template = $data['senderName'];
            $data['senderName'] = $this->templateRenderer->render($template, $templateData, $context, false);
            foreach ($contents as $index => $template) {
                $contents[$index] = $this->templateRenderer->render($template, $templateData, $context, $index !== 'text/plain');
            }
        } catch (\Throwable $e) {
            $event = new MailErrorEvent(
                $context,
                Level::Error,
                $e,
                'Could not render Mail-Template with error message: ' . $e->getMessage(),
                $template,
                $templateData
            );
            $this->eventDispatcher->dispatch($event);
            $this->logger->error(
                'Could not render Mail-Template with error message: ' . $e->getMessage(),
                array_merge([
                    'template' => $template,
                    'exception' => (string) $e,
                ], $templateData)
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

        if (trim($mail->getBody()->toString()) === '') {
            $event = new MailErrorEvent(
                $context,
                Level::Error,
                null,
                'mail body is null',
                null,
                $templateData
            );

            $this->eventDispatcher->dispatch($event);
            $this->logger->error(
                'mail body is null',
                $templateData
            );

            return null;
        }

        $event = new MailBeforeSentEvent($data, $mail, $context, $templateData['eventName'] ?? null);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return null;
        }

        $this->mailSender->send($mail);

        $event = new MailSentEvent($data['subject'], $recipients, $contents, $context, $templateData['eventName'] ?? null);
        $this->eventDispatcher->dispatch($event);

        return $mail;
    }

    /**
     * @param mixed[] $data
     */
    private function getSender(array $data, ?string $salesChannelId): ?string
    {
        $senderEmail = $data['senderEmail'] ?? null;

        if ($senderEmail !== null && trim((string) $senderEmail) !== '') {
            return $senderEmail;
        }

        $senderEmail = $this->systemConfigService->getString('core.basicInformation.email', $salesChannelId);

        if (trim($senderEmail) !== '') {
            return $senderEmail;
        }

        $senderEmail = $this->systemConfigService->getString('core.mailerSettings.senderAddress', $salesChannelId);

        if (trim($senderEmail) !== '') {
            return $senderEmail;
        }

        return null;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param mixed[] $data
     * e.g. ['contentHtml' => 'foobar', 'contentPlain' => '<h1>foobar</h1>']
     *
     * @return mixed[]
     * e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     *
     * @internal
     */
    private function buildContents(array $data, ?SalesChannelEntity $salesChannel): array
    {
        if ($salesChannel && $mailHeaderFooter = $salesChannel->getMailHeaderFooter()) {
            $headerPlain = $mailHeaderFooter->getTranslation('headerPlain') ?? '';
            \assert(\is_string($headerPlain));
            $footerPlain = $mailHeaderFooter->getTranslation('footerPlain') ?? '';
            \assert(\is_string($footerPlain));
            $headerHtml = $mailHeaderFooter->getTranslation('headerHtml') ?? '';
            \assert(\is_string($headerHtml));
            $footerHtml = $mailHeaderFooter->getTranslation('footerHtml') ?? '';
            \assert(\is_string($footerHtml));

            \assert(\is_string($data['contentPlain']));
            \assert(\is_string($data['contentHtml']));

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

    /**
     * @param mixed[] $data
     *
     * @return string[]
     */
    private function getMediaUrls(array $data, Context $context): array
    {
        if (!isset($data['mediaIds']) || empty($data['mediaIds'])) {
            return [];
        }
        $criteria = new Criteria($data['mediaIds']);
        $criteria->setTitle('mail-service::resolve-media-ids');
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
        $criteria->setTitle('mail-service::resolve-sales-channel-domain');
        $criteria->addAssociation('mailHeaderFooter');
        $criteria->getAssociation('domains')
            ->addFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );

        return $criteria;
    }

    /**
     * @param mixed[] $data
     */
    private function isTestMode(array $data = []): bool
    {
        return isset($data['testMode']) && (bool) $data['testMode'] === true;
    }

    /**
     * @param mixed[] $templateData
     */
    private function templateDataContainsSalesChannel(array $templateData): bool
    {
        return isset($templateData['salesChannel']) && $templateData['salesChannel'] instanceof SalesChannelEntity;
    }
}
