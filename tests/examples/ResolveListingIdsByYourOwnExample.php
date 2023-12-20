<?php

namespace Shopware\Tests\Examples;

use GuzzleHttp\Client;
use Shopware\Core\Content\Product\Extension\ResolveListingLoaderIdsExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(
    event: 'listing-loader.resolve-listing-ids.pre',
    method: '__invoke'
)]
#[Title('Example how you control the listing product ids')]
#[Description('This example shows how you can control the listing product ids. It allows you to resolve the listing ids by your own over an API call or an own storage')]
readonly class ResolveListingIdsByYourOwnExample
{
    public function __construct(
        // you can inject your own services
        private Client $client
    ) {}

    public function __invoke(ResolveListingLoaderIdsExtension $event): void
    {
        $criteria = $event->criteria;

        $context = [
            'salesChannelId' => $event->context->getSalesChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // use this client to do get request against: https://your-api.com/listing-ids?criteria=...&context=...

        $ids = $this->client->get('https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents());

        $result = IdSearchResult::fromIds(
            $data['ids'],
            $event->criteria,
            $event->context->getContext(),
            $data['total']
        );

        $event->result = $result;
    }
}
