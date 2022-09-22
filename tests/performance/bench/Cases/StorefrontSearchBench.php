<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Cases;

use PhpBench\Attributes as Bench;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Tests\Bench\BenchCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal - only for performance benchmarks
 */
class StorefrontSearchBench extends BenchCase
{
    public function setUp(): void
    {
        parent::setup();

        $rulePayload = [];

        for ($i = 0; $i < 1500; ++$i) {
            $rulePayload[] = [
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

        $this->getContainer()->get('rule.repository')
            ->create($rulePayload, $this->context->getContext());

        // this will update the rule ids inside the context
        $this->getContainer()->get(CartRuleLoader::class)->loadByToken($this->context, 'bench');
    }

    #[Bench\Assert('mode(variant.time.avg) < 75ms')]
    public function bench_searching_with_1500_active_rules(): void
    {
        $request = Request::create('/search?search=Simple', 'GET', ['search' => 'Simple']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'localhost');

        $this->getContainer()->get('request_stack')->push($request);

        $this->getContainer()->get(SearchPageLoader::class)
            ->load($request, $this->context);
    }
}
