<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\Subscriber\ActiveRulesDataCollectorSubscriber;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ActiveRulesDataCollectorSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
    }

    public function testDataCollection(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 100]],
            Context::createDefaultContext()
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = new Context(new SystemSource(), [$ruleId]);
        $salesChannelContext->method('getContext')->willReturn($context);
        $event = new SalesChannelContextResolvedEvent($salesChannelContext, Uuid::randomHex());

        $subscriber = new ActiveRulesDataCollectorSubscriber($this->ruleRepository);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        $data = $subscriber->getData();

        static::assertEquals(1, $subscriber->getMatchingRuleCount());
        static::assertArrayHasKey($ruleId, $data);

        $rule = $data[$ruleId];
        static::assertInstanceOf(RuleEntity::class, $rule);
        static::assertEquals(100, $rule->getPriority());
        static::assertEquals('Demo rule', $rule->getName());
    }
}
