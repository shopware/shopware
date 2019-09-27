<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerBeforeLoginEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'checkout.customer.before.login';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(Context $context, string $email, string $salesChannelId)
    {
        $this->email = $email;
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('email', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
