<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

trait GoogleAPIMockResponse
{
    protected function getExpectedResponse(string $expectedClass): array
    {
        switch ($expectedClass) {
            case \Google_Service_ShoppingContent_Account::class:
                return [
                    'adultContent' => false,
                    'id' => '12345678',
                    'kind' => 'content#account',
                    'name' => 'Test merchant',
                    'websiteUrl' => "http:\/\/shopware.test",
                ];

            case \Google_Service_ShoppingContent_AccountsListResponse::class:
                return [
                    'kind' => 'content#accountsListResponse',
                    'resources' => [
                        [
                            'kind' => 'content#account',
                            'id' => '123123123',
                            'name' => 'Demoshop 1',
                            'websiteUrl' => 'http://shopwarelocal.test',
                            'adultContent' => false,
                            'businessInformation' => [
                                'address' => [
                                    'country' => 'EN',
                                ],
                            ],
                        ],
                        [
                            'kind' => 'content#account',
                            'id' => '123456789',
                            'name' => 'Demoshop 2',
                            'websiteUrl' => 'http://shopware.test/',
                            'adultContent' => false,
                        ],
                    ],
                ];

            case \Google_Service_ShoppingContent_AccountStatus::class:
                return $this->getAccountStatusResponse();

            case \Google_Service_SiteVerification_Resource_WebResource::class:
                return [
                    'id' => 'https%3A%2F%shopware.test%2F',
                    'owners' => [
                        'john.doe@example.com',
                        'jane.doe@example.com',
                    ],
                    'site' => [
                        'identifier' => 'http://shopware.test/',
                        'type' => 'SITE',
                    ],
                ];

            case \Google_Service_ShoppingContent_AccountsClaimWebsiteResponse::class:
                return [
                    'kind' => 'content#accountsClaimWebsiteResponse',
                ];

            case \Google_Service_Oauth2_Userinfo::class:
                return [
                    'id' => '1234567890',
                    'email' => 'john.doe@example.com',
                    'verified_email' => true,
                    'name' => 'John Doe',
                    'given_name' => 'John',
                    'family_name' => 'Joe',
                    'picture' => 'https://lh3.googleusercontent.com/a-/AOh14Ghvc3v9xTUIDTCW67gcdolbfBlHMoHYSFLc6hglZA',
                    'locale' => 'en',
                ];

            case \Google_Service_ShoppingContent_Datafeed::class:
                return [
                    'contentType' => 'products',
                    'fileName' => '1bdcd3ae03b74ab28d67c2c8571ab060',
                    'id' => '123456789',
                    'kind' => 'content#datafeed',
                    'name' => 'Demoshop',
                    'format' => [
                        'quotingMode' => 'value quoting',
                    ],
                    'targets' => [
                        [
                            'country' => 'DE',
                            'language' => 'de',
                            'includedDestinations' => [
                                'Shopping',
                            ],
                        ],
                    ],
                ];

            case \Google_Service_ShoppingContent_DatafeedStatus::class:
                return [
                    'country' => 'DE',
                    'datafeedId' => '123456789',
                    'itemsTotal' => '50',
                    'itemsValid' => '10',
                    'kind' => 'content#datafeedStatus',
                    'language' => 'en',
                    'lastUploadDate' => ' 2020-04-17T04:01:37Z',
                    'processingStatus' => 'success',
                ];

            case \Google_Service_ShoppingContent_AccountsAuthInfoResponse::class:
                return [
                    'kind' => 'content#accountsAuthInfoResponse',
                    'accountIdentifiers' => [
                        [
                            'aggregatorId' => '1234567890',
                            'merchantId' => '1234567891',
                        ], [
                            'aggregatorId' => '1234567892',
                            'merchantId' => '1234567893',
                        ], [
                            'aggregatorId' => '1234567894',
                        ], [
                            'aggregatorId' => '1234567895',
                            'merchantId' => '1234567896',
                        ],
                    ],
                ];

            case \Google_Service_ShoppingContent_ProductstatusesCustomBatchResponse::class:
                return $this->getProductStatusesBatchResponse();

            default:
                return [];
        }
    }

    private function getProductStatusesBatchResponse(): array
    {
        return [
            'kind' => 'content#productstatusesCustomBatchResponse',
            'entries' => [
                [
                    'kind' => 'content#productstatusesCustomBatchResponseEntry',
                    'batchId' => 0,
                    'productStatus' => [
                        'kind' => 'content#productStatus',
                        'productId' => 'online:en:EN:7498971eabb14b44a89df9a115a27aee',
                        'title' => 'Sleek Granite SkyBag',
                        'destinationStatuses' => [
                            [
                                'destination' => 'Shopping',
                                'status' => 'disapproved',
                            ],
                        ],
                        'itemLevelIssues' => [
                            [
                                'code' => 'hard_goods_missing_2_out_of_3_identifiers',
                                'servability' => 'unaffected',
                                'resolution' => 'merchant_action',
                                'destination' => 'Shopping',
                                'description' => 'Limited performance due to missing identifiers [gtin, mpn, brand]',
                                'detail' => 'Provide at least 2 of the missing identifiers',
                                'documentation' => 'https://support.google.com/merchants/answer/6098295',
                            ],
                            [
                                'code' => 'pending_initial_policy_review',
                                'servability' => 'disapproved',
                                'resolution' => 'pending_processing',
                                'destination' => 'Shopping',
                                'description' => 'Pending initial review',
                                'documentation' => 'https://support.google.com/merchants/answer/2948694',
                            ],
                            [
                                'code' => 'invalid_url',
                                'servability' => 'disapproved',
                                'resolution' => 'merchant_action',
                                'attributeName' => 'image link',
                                'destination' => 'Shopping',
                                'description' => 'Invalid URL [image link]',
                                'detail' => 'Use a complete URL that starts with http:// or https:// and links to a valid destination such as an image or a landing page',
                                'documentation' => 'https://support.google.com/merchants/answer/7052112',
                            ],
                            [
                                'code' => 'url_does_not_match_homepage',
                                'servability' => 'disapproved',
                                'resolution' => 'merchant_action',
                                'attributeName' => 'link',
                                'destination' => 'Shopping',
                                'description' => 'Mismatched domains [link]',
                                'detail' => 'Use the same domain for product landing page URLs as in your Merchant Center website setting',
                                'documentation' => 'https://support.google.com/merchants/answer/160050',
                            ],
                        ],
                        'creationDate' => '2020-04-21T13:00:15Z',
                        'lastUpdateDate' => '2020-04-21T13:00:15Z',
                        'googleExpirationDate' => '2020-05-21T13:00:15Z',
                    ],
                ],
                [
                    'kind' => 'content#productstatusesCustomBatchResponseEntry',
                    'batchId' => 1,
                    'productStatus' => [
                        'kind' => 'content#productStatus',
                        'productId' => 'online:en:EN:7498971eabb14b44a89df9a115a27aef',
                        'title' => 'Awesome Clothes',
                        'destinationStatuses' => [
                            [
                                'destination' => 'Shopping',
                                'status' => 'approved',
                            ],
                        ],
                        'creationDate' => '2020-04-21T13:00:15Z',
                        'lastUpdateDate' => '2020-04-21T13:00:15Z',
                        'googleExpirationDate' => '2020-05-21T13:00:15Z',
                    ],
                ],
                [
                    'kind' => 'content#productstatusesCustomBatchResponseEntry',
                    'batchId' => 2,
                    'productStatus' => [
                        'kind' => 'content#productStatus',
                        'productId' => 'online:en:EN:7498971eabb14b44a89df9a115a27aeg',
                        'title' => 'Random Computer',
                        'destinationStatuses' => [
                            [
                                'destination' => 'Shopping',
                                'status' => 'pending',
                            ],
                        ],
                        'creationDate' => '2020-04-21T13:00:15Z',
                        'lastUpdateDate' => '2020-04-21T13:00:15Z',
                        'googleExpirationDate' => '2020-05-21T13:00:15Z',
                    ],
                ],
            ],
        ];
    }

    private function getAccountStatusResponse(): array
    {
        return [
            'accountId' => '123456789',
            'kind' => 'content#accountStatus',
            'websiteClaimed' => false,
            'accountLevelIssues' => [
                [
                    'documentation' => "https:\/\/support.google.com\/merchants\/answer\/176793",
                    'id' => 'home_page_issue',
                    'severity' => 'critical',
                    'title' => 'Website not claimed',
                ],
                [
                    'documentation' => "https:\/\/support.google.com\/merchants\/answer\/6159060",
                    'id' => 'missing_ad_words_link',
                    'severity' => 'error',
                    'title' => 'Pending Google Ads account link request',
                ],
            ],
            'products' => [
                [
                    'channel' => 'online',
                    'country' => 'DE',
                    'destination' => 'Shopping',
                    'statistics' => [
                        'active' => '0',
                        'disapproved' => '120',
                        'expiring' => '0',
                        'pending' => '0',
                    ],
                    'itemLevelIssues' => [
                        [
                            'attributeName' => 'tax',
                            'code' => 'missing_tax',
                            'description' => 'Missing value [tax]',
                            'detail' => 'Add tax information in your Merchant Center settings or in your product data',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/7052209",
                            'numItems' => '120',
                            'resolution' => 'merchant_action',
                            'servability' => 'disapproved',
                        ],
                        [
                            'attributeName' => 'link',
                            'code' => 'url_does_not_match_homepage',
                            'description' => 'Mismatched domains [link]',
                            'detail' => 'Use the same domain for product landing page URLs as in your Merchant Center website setting',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/160050",
                            'numItems' => '120',
                            'resolution' => 'merchant_action',
                            'servability' => 'disapproved',
                        ],
                        [
                            'attributeName' => 'image link',
                            'code' => 'image_link_broken',
                            'description' => 'Invalid image [image link]',
                            'detail' => 'Ensure the image is accessible and uses an accepted image format (JPEG, PNG, GIF)',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/6098289",
                            'numItems' => '120',
                            'resolution' => 'merchant_action',
                            'servability' => 'disapproved',
                        ],
                        [
                            'code' => 'pending_initial_policy_review',
                            'description' => 'Pending initial review',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/2948694",
                            'numItems' => '120',
                            'resolution' => 'pending_processing',
                            'servability' => 'disapproved',
                        ],
                    ],
                ],
                [
                    'channel' => 'online',
                    'country' => 'DE',
                    'destination' => 'Shopping',
                    'statistics' => [
                        'active' => '0',
                        'disapproved' => '60',
                        'expiring' => '0',
                        'pending' => '0',
                    ],
                    'itemLevelIssues' => [
                        [
                            'code' => 'hard_goods_missing_2_out_of_3_identifiers',
                            'description' => 'Limited performance due to missing identifiers [gtin, mpn, brand]',
                            'detail' => 'Provide at least 2 of the missing identifiers',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/6098295",
                            'numItems' => '60',
                            'resolution' => 'merchant_action',
                            'servability' => 'unaffected',
                        ],
                        [
                            'attributeName' => 'link',
                            'code' => 'url_does_not_match_homepage',
                            'description' => 'Mismatched domains [link]',
                            'detail' => 'Use the same domain for product landing page URLs as in your Merchant Center website setting',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/160050",
                            'numItems' => '60',
                            'resolution' => 'merchant_action',
                            'servability' => 'disapproved',
                        ],
                        [
                            'attributeName' => 'image link',
                            'code' => 'invalid_url',
                            'description' => 'Invalid URL [image link]',
                            'detail' => "Use a complete URL that starts with http:\/\/ or https:\/\/ and links to a valid destination such as an image or a landing page",
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/7052112",
                            'numItems' => '60',
                            'resolution' => 'merchant_action',
                            'servability' => 'disapproved',
                        ],
                        [
                            'code' => 'pending_initial_policy_review',
                            'description' => 'Pending initial review',
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/2948694",
                            'numItems' => '60',
                            'resolution' => 'pending_processing',
                            'servability' => 'disapproved',
                        ],
                    ],
                ],
            ],
        ];
    }
}
