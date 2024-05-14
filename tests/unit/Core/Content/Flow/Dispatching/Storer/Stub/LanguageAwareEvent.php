<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\LanguageAware;

/**
 * @internal
 */
class LanguageAwareEvent implements FlowEventAware, LanguageAware
{
    public function __construct(private readonly ?string $languageId)
    {
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('languageId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }
}
