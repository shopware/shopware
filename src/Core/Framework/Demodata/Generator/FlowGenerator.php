<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceEntity;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Api\FlowActionDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('core')]
class FlowGenerator implements DemodataGeneratorInterface
{
    /**
     * @var array<string, array<string>>
     */
    private array $ids = [];

    /**
     * @var array<string, FlowActionDefinition>
     */
    private array $actions = [];

    /**
     * @var array<string, array{id: string}>
     */
    private array $tags = [];

    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly BusinessEventCollector $eventCollector,
        private readonly FlowActionCollector $flowActionCollector
    ) {
    }

    public function getDefinition(): string
    {
        return FlowDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createFlows($context->getContext(), $numberOfItems);
    }

    private function createFlows(Context $context, int $count): void
    {
        $this->io->progressStart($count);

        $this->createTags($context);

        $events = $this->eventCollector->collect($context)->getElements();

        $payload = [];

        $maxSequenceTree = 5;
        $maxSequencePerTree = 10;

        $eventNames = [];

        for ($i = 0; $i < $count; ++$i) {
            /** @var BusinessEventDefinition $event */
            $event = $this->faker->randomElement($events);

            $eventNames[$event->getName()] = \array_key_exists($event->getName(), $eventNames) ? ($eventNames[$event->getName()] + 1) : 1;

            $flow = [
                'id' => Uuid::randomHex(),
                'name' => $this->generateFlowName($event->getName(), $eventNames[$event->getName()]),
                'eventName' => $event->getName(),
                'priority' => $i + 1,
                'active' => true,
            ];

            $sequenceTreeCount = random_int(1, $maxSequenceTree);

            $sequences = new FlowSequenceCollection();

            for ($t = 0; $t < $sequenceTreeCount; ++$t) {
                $position = 1;

                $displayGroup = $t + 1;

                $sequenceCount = random_int(1, $maxSequencePerTree);

                $sequence = new FlowSequenceEntity();
                $sequence->setId(Uuid::randomHex());
                $sequence->setDisplayGroup($displayGroup);
                $sequence->setPosition($position);
                $sequence->setTrueCase(true);
                $sequence->setFlowId($flow['id']);
                $sequence->assign([
                    'ruleId' => null,
                    'actionName' => null,
                ]);

                $isRoot = true;

                for ($s = 0; $s < $sequenceCount; ++$s) {
                    $parent = $sequence;

                    $falseCase = null;

                    if ($parent->getRuleId()) {
                        $generateFalseCase = $this->faker->boolean();

                        if ($generateFalseCase) {
                            $falseCase = $this->buildRandomSequence($parent, false);

                            $sequences->add($falseCase);

                            $parent->setPosition($parent->getPosition() + 1);
                        }
                    }

                    $sequence = $this->buildRandomSequence($parent);

                    if ($isRoot) {
                        $sequence->assign([
                            'parentId' => null,
                        ]);

                        $isRoot = false;
                    }

                    $sequences->add($sequence);

                    if ($falseCase === null) {
                        continue;
                    }

                    $buildFalsePath = $this->faker->boolean();

                    $action = $this->buildActionSequence($buildFalsePath ? $sequence : $falseCase, $buildFalsePath);
                    $sequences->add($action);

                    $sequence = $buildFalsePath ? $falseCase : $sequence;
                }

                // Add a action if the last sequence tree is an if condition
                if ($sequences->last() && $sequences->last()->getRuleId() !== null) {
                    $action = $this->buildActionSequence($sequences->last(), $this->faker->boolean());
                    $sequences->add($action);
                }
            }

            $sequences = json_decode((string) json_encode($sequences->jsonSerialize(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

            $sequences = array_map(fn (array $sequence) => array_filter($sequence), $sequences);

            $flow['sequences'] = $sequences;
            $payload[] = $flow;

            if (\count($payload) >= 20) {
                $this->io->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $this->io->progressFinish();
    }

    private function buildRandomSequence(FlowSequenceEntity $parent, bool $trueCase = true): FlowSequenceEntity
    {
        if ($this->faker->boolean()) {
            return $this->buildIfSequence($parent, $trueCase);
        }

        return $this->buildActionSequence($parent, $trueCase);
    }

    private function buildIfSequence(FlowSequenceEntity $parent, bool $trueCase = true): FlowSequenceEntity
    {
        $ruleIds = $this->getIds('rule');

        if ($parent->getActionName() !== null) {
            return $this->buildActionSequence($parent);
        }

        $randomRuleId = $this->faker->randomElement($ruleIds);

        $sequence = FlowSequenceEntity::createFrom($parent);
        $sequence->setId(Uuid::randomHex());
        $sequence->setParentId($parent->getId());
        $sequence->setRuleId($randomRuleId);
        $sequence->setPosition($parent->getPosition() + 1);
        $sequence->setTrueCase($trueCase);
        $sequence->assign([
            'actionName' => null,
        ]);

        $this->ids['rule'] = array_filter($ruleIds, fn ($ruleId) => $ruleId !== $randomRuleId);

        return $sequence;
    }

    private function buildActionSequence(FlowSequenceEntity $parent, bool $trueCase = true): FlowSequenceEntity
    {
        $actions = $this->getActions();

        $sequence = FlowSequenceEntity::createFrom($parent);

        /** @var FlowActionDefinition $action */
        $action = $this->faker->randomElement($actions);
        $sequence->setId(Uuid::randomHex());

        $sequence->setParentId($parent->getId());
        $sequence->setActionName($action->getName());
        $sequence->setConfig($this->generateActionConfig($action->getName()));
        $sequence->setTrueCase($trueCase);
        $sequence->setPosition($parent->getPosition() + 1);

        if ($parent->getActionName()) {
            $sequence->assign([
                'parentId' => $parent->getParentId(),
                'trueCase' => $parent->isTrueCase(),
            ]);
        }

        $sequence->assign([
            'ruleId' => null,
        ]);

        unset($this->actions[$action->getName()]);

        return $sequence;
    }

    /**
     * @return array<string, FlowActionDefinition>
     */
    private function getActions(): array
    {
        if (!empty($this->actions)) {
            return $this->actions;
        }

        return $this->actions = $this->flowActionCollector->collect(Context::createDefaultContext())->getElements();
    }

    // Randomly create 10 tags
    private function createTags(Context $context): void
    {
        $tagRepository = $this->registry->getRepository('tag');

        $tags = $tagRepository->searchIds(new Criteria(), $context)->firstId();

        if ($tags !== null) {
            return;
        }

        $payload = [];

        for ($i = 0; $i < 10; ++$i) {
            $payload[] = [
                'name' => $this->faker->word(),
            ];
        }

        $tagRepository->create($payload, $context);
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $this->registry->getRepository('flow')->create($payload, $context);
    }

    /**
     * @return array<string>
     */
    private function getIds(string $table): array
    {
        if (!empty($this->ids[$table])) {
            return $this->ids[$table];
        }

        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');

        return $this->ids[$table] = $ids;
    }

    /**
     * @return non-empty-array<string, array{id: string}>
     */
    private function getTags(): array
    {
        if (!empty($this->tags)) {
            return $this->tags;
        }

        /** @var non-empty-array<string, array{id: string}> $result */
        $result = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), name as id FROM tag LIMIT 500');

        return $this->tags = $result;
    }

    private function generateFlowName(string $event, int $num): string
    {
        return str_replace(['.', '_'], ' ', ucfirst($event) . ' #' . (string) $num);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateActionConfig(string $action): array
    {
        $tagIds = $this->getTags();

        return match ($action) {
            RemoveOrderTagAction::getName(), AddOrderTagAction::getName() => [
                'entity' => 'order',
                'tagIds' => $this->faker->randomElements($tagIds, random_int(1, \count($tagIds))),
            ],
            AddCustomerTagAction::getName() => [
                'entity' => 'customer',
                'tagIds' => $this->faker->randomElements($tagIds, random_int(1, \count($tagIds))),
            ],
            RemoveCustomerTagAction::getName() => [
                'entity' => 'customer',
                'tagIds' => $this->faker->randomElements($tagIds, random_int(1, \count($tagIds))),
            ],
            GenerateDocumentAction::getName() => [
                'documentType' => 'invoice',
                'documentRangerType' => 'document_invoice',
            ],
            SendMailAction::getName() => [
                'mailTemplateId' => $this->faker->randomElement($this->getIds('mail_template')),
            ],
            SetOrderStateAction::getName() => [
                'order' => $this->faker->randomElement([
                    OrderStates::STATE_OPEN,
                    OrderStates::STATE_IN_PROGRESS,
                    OrderStates::STATE_CANCELLED,
                    OrderStates::STATE_COMPLETED,
                ]),
                'order_delivery' => $this->faker->randomElement([
                    OrderDeliveryStates::STATE_OPEN,
                    OrderDeliveryStates::STATE_SHIPPED,
                    OrderDeliveryStates::STATE_PARTIALLY_RETURNED,
                    OrderDeliveryStates::STATE_PARTIALLY_SHIPPED,
                    OrderDeliveryStates::STATE_RETURNED,
                ]),
                'order_transaction' => $this->faker->randomElement([
                    OrderTransactionStates::STATE_OPEN,
                    OrderTransactionStates::STATE_IN_PROGRESS,
                    OrderTransactionStates::STATE_AUTHORIZED,
                    OrderTransactionStates::STATE_FAILED,
                    OrderTransactionStates::STATE_PARTIALLY_PAID,
                    OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
                    OrderTransactionStates::STATE_REFUNDED,
                    OrderTransactionStates::STATE_REMINDED,
                    OrderTransactionStates::STATE_PAID,
                    OrderTransactionStates::STATE_CANCELLED,
                ]),
            ],
            default => [],
        };
    }
}
