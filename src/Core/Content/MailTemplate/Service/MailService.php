<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

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
use Symfony\Component\Validator\Constraints\NotBlank;

class MailService
{
    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var MailBuilder
     */
    private $mailBuilder;

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

    public function __construct(
        DataValidator $dataValidator,
        MailBuilder $mailBuilder,
        StringTemplateRenderer $templateRenderer,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        EntityRepositoryInterface $mediaRepository,
        SalesChannelDefinition $salesChannelDefinition,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->dataValidator = $dataValidator;
        $this->mailBuilder = $mailBuilder;
        $this->templateRenderer = $templateRenderer;
        $this->messageFactory = $messageFactory;
        $this->mailSender = $mailSender;
        $this->mediaRepository = $mediaRepository;
        $this->salesChannelDefinition = $salesChannelDefinition;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function send(array $data, Context $context, array $templateData = []): \Swift_Message
    {
        $definition = $this->getValidationDefinition($context);
        $this->dataValidator->validate($data, $definition);

        $recipients = $data['recipients'];
        $salesChannelId = $data['salesChannelId'];

        //todo exception
        $criteria = new Criteria([$salesChannelId]);
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        $bodies = [
            'text/html' => $data['contentHtml'],
            'text/plain' => $data['contentPlain'],
        ];

        $contents = $this->mailBuilder->buildContents($context, $bodies, $salesChannelId);
        $templateData['salesChannel'] = $salesChannel;
        foreach ($contents as $index => $template) {
            $contents[$index] = $this->templateRenderer->render($template, $templateData);
        }

        $mediaUrls = $this->getMediaUrls($data['mediaIds'], $context);

        $message = $this->messageFactory->createMessage(
            $data['subject'],
            [$data['senderMail'] => $data['senderName']],
            $recipients,
            $contents,
            $mediaUrls
        );

        $this->mailSender->send($message);

        return $message;
    }

    private function getValidationDefinition(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('recipients', new NotBlank());
        $definition->add('salesChannelId', new EntityExists(['entity' => $this->salesChannelDefinition, 'context' => $context]));
        $definition->add('contentHtml', new NotBlank());
        $definition->add('contentPlain', new NotBlank());
        $definition->add('subject', new NotBlank());
        $definition->add('senderMail', new NotBlank());
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
