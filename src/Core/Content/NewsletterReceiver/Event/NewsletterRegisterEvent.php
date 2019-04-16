<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\Event;

use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverDefinition;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Component\EventDispatcher\Event;

class NewsletterRegisterEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'newsletter.register';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var NewsletterReceiverEntity
     */
    private $receiverEntity;

    /**
     * @var string
     */
    private $url;

    public function __construct(Context $context, NewsletterReceiverEntity $receiverEntity, string $url)
    {
        $this->context = $context;
        $this->receiverEntity = $receiverEntity;
        $this->url = $url;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('receiverEntity', new EntityType(NewsletterReceiverDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getReceiverEntity(): NewsletterReceiverEntity
    {
        return $this->receiverEntity;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
