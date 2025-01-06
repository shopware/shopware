<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Cases;

use PhpBench\Attributes as Bench;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Tests\Bench\AbstractBenchCase;

/**
 * @internal - only for performance benchmarks
 */
class RuleAreaUpdaterBench extends AbstractBenchCase
{
    public function setUp(): void
    {
        parent::setup();

        $rulePayload = [];

        for ($i = 0; $i < 100; ++$i) {
            $rulePayload[] = [
                'id' => $this->ids->get('rule-' . $i),
                'name' => 'test' . $i,
                'priority' => $i,
                'conditions' => [
                    [
                        'type' => 'andContainer',
                        'children' => [
                            [
                                'type' => 'alwaysValid',
                            ],
                        ],
                    ],
                ],
            ];
        }

        $context = $this->context->getContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        static::getContainer()->get('rule.repository')->create($rulePayload, $context);
    }

    #[Bench\Assert('mode(variant.time.avg) < 50ms +/- 10ms')]
    public function bench_updating_areas_with_100_rules(): void
    {
        static::getContainer()->get(RuleAreaUpdater::class)->update(array_values($this->ids->prefixed('rule-')));
    }
}
