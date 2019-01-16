<?php declare(strict_types=1);

return [
    // RESULT 1
    'result1' => [
        'unit_TEST' => [
                'snippets' => [
                        'only.possible.with.unitTests.test1' => 'this is test 1.',
                        'only.possible.with.unitTests.test2' => 'this is test 2.',
                        'only.possible.with.unitTests.test3' => 'this is test 3.',
                        'only.possible.with.unitTests.test4' => 'this is test 4.',
                        'only.possible.with.unitTests.test5' => 'this is test 5.',
                        'only.possible.with.unitTests.test6' => 'this is test 6.',
                        'only.possible.with.unitTests.test7' => 'this is test 7.',
                        'only.possible.with.unitTests.test8' => 'this is test 8.',
                    ],
            ],
    ],

    // RESULT 2
    'result2' => [
        'de_DE' => [
                'snippets' => [
                        'general.disabledCookiesNotice' => 'Es wurde festgestellt, dass Cookies in Ihrem Browser deaktiviert sind. Um {$sShopname|escapeJs} in vollem Umfang nutzen zu können, empfehlen wir Ihnen, Cookies in Ihrem Browser zu aktiveren.',
                        'general.disabledJavascriptNotice' => 'Um {$sShopname|escapeHtmlAttr} in vollem Umfang nutzen zu k&ouml;nnen, empfehlen wir Ihnen Javascript in Ihrem Browser zu aktiveren.',
                        'general.homeLink' => 'Home',
                        'general.menuCategoryHeadline' => 'Kategorien',
                        'general.menuClose' => 'Menü schließen',
                        'general.menuLink' => 'Menü',
                        'general.priceStar' => '*',
                        'general.searchFieldPlaceholder' => 'Suchbegriff ...',
                    ],
            ],
    ],

    // RESULT 3
    'result3' => [
        'en_GB' => [
                'snippets' => [
                        'general.disabledCookiesNotice' => 'We have detected that cookies are disabled in your browser. To be able to use {$sShopname|escapeJs} in full range, we recommend activating cookies in your browser.',
                        'general.disabledJavascriptNotice' => 'To be able to use {$sShopname|escapeHtmlAttr} in full range, we recommend activating Javascript in your browser.',
                        'general.homeLink' => 'Home',
                        'general.menuCategoryHeadline' => 'Categories',
                        'general.menuClose' => 'Close menu',
                        'general.menuLink' => 'Menu',
                        'general.priceStar' => '*',
                        'general.searchFieldPlaceholder' => 'Search ...',
                    ],
            ],
    ],

    // RESULT 4
    'result4' => [
        'unit_TEST' => [
                'snippets' => [
                        'only.possible.with.unitTests.test1' => 'this is test 1.',
                        'only.possible.with.unitTests.test2' => 'this is test 2.',
                        'only.possible.with.unitTests.test3' => 'this is test 3.',
                        'only.possible.with.unitTests.test4' => 'this is test 4.',
                        'only.possible.with.unitTests.test5' => 'this is test 5.',
                        'only.possible.with.unitTests.test6' => 'this is test 6.',
                        'only.possible.with.unitTests.test7' => 'this is test 7.',
                        'only.possible.with.unitTests.test8' => 'this is test 8.',
                        'detail.descriptionHeader' => '',
                        'detail.configSubmit' => '',
                        'footer.copyright' => '',
                        'detail.buyAddButton' => '',
                    ],
            ],
        'en_GB' => [
                'snippets' => [
                        'detail.descriptionHeader' => 'Just another test string',
                        'detail.configSubmit' => 'A new test string',
                        'footer.copyright' => 'Only possible with unit tests',
                        'detail.buyAddButton' => 'This is a test string',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'detail.buyAddButton' => 'Das ist ein Test String',
                        'detail.configSubmit' => 'Ein neuer test String',
                        'footer.copyright' => 'Nur möglich mit Unititests',
                        'detail.descriptionHeader' => 'Nur ein weiterer test String',
                    ],
            ],
    ],

    // RESULT 5
    'result5' => [
        'de_DE' => [
                'snippets' => [
                        'frontend.note.item.NoteLinkDelete' => 'Löschen',
                        'frontend.checkout.cart_item.CartItemLinkDelete' => 'Löschen',
                        'frontend.address.index.AddressesDeleteNotice' => '<b>Hinweis:</b> Das Löschen dieser Adresse wirkt sich nicht auf bestehende Bestellungen zu dieser Adresse aus.',
                        'frontend.address.index.AddressesDeleteTitle' => 'Adresse löschen',
                        'frontend.address.index.AddressesContentItemActionDelete' => 'Löschen',
                        'frontend.compare.index.CompareActionDelete' => 'Vergleich löschen',
                    ],
            ],
    ],
];
