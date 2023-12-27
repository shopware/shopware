<?php

namespace Shopware\Tests\Examples;

use GuzzleHttp\Client;
use Shopware\Core\Content\Product\Extension\ResolveListingLoaderIdsExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @title Example how you control the listing product ids
 * @description This example shows how you can control the listing product ids. It allows you to resolve the listing ids by your own over an API call or an own storage
 */
#[AsEventListener(
    event: ResolveListingLoaderIdsExtension::PRE,
    method: '__invoke'
)]
readonly class ResolveListingIdsByYourOwnExample
{
    public function __construct(
        // you can inject your own services
        private Client $client
    ) {}

    public function __invoke(ResolveListingLoaderIdsExtension $event): void
    {
        $criteria = $event->criteria;

        // building a json aware array for the API call
        $context = [
            'salesChannelId' => $event->context->getSalesChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // do an api call against your own server or another storage, or whatever you want
        $ids = $this->client->get('https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents());

        // create the expected result
        $result = IdSearchResult::fromIds(
            $data['ids'],
            $event->criteria,
            $event->context->getContext(),
            $data['total']
        );

        $event->result = $result;

        // stop the event propagation, so the default behaviour will not be executed
        $event->stopPropagation();
    }
}
