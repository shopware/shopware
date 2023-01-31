<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Subscriber;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class ActiveRulesDataCollectorSubscriber extends AbstractDataCollector implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string>
     */
    private array $ruleIds = [];

    public function __construct(private readonly EntityRepository $ruleRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextResolvedEvent::class => 'onContextResolved',
        ];
    }

    public function reset(): void
    {
        parent::reset();
        $this->ruleIds = [];
    }

    /**
     * @return array<string, RuleEntity>|Data<string, RuleEntity>
     */
    public function getData(): array|Data
    {
        return $this->data;
    }

    public function getMatchingRuleCount(): int
    {
        if ($this->data instanceof Data) {
            return $this->data->count();
        }

        return \count($this->data);
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = $this->getMatchingRules();
    }

    public static function getTemplate(): string
    {
        return '@Profiling/Collector/rules.html.twig';
    }

    public function onContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $this->ruleIds = $event->getContext()->getRuleIds();
    }

    /**
     * @return array<string, Entity>
     */
    private function getMatchingRules(): array
    {
        if (empty($this->ruleIds)) {
            return [];
        }

        $criteria = new Criteria($this->ruleIds);

        return $this->ruleRepository->search($criteria, Context::createDefaultContext())->getElements();
    }
}
