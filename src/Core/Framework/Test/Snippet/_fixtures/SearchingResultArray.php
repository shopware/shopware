<?php declare(strict_types=1);

return [
    // RESULT 1
    'result1' => [
        'en_GB' => [
                'snippets' => [
                        'detail.buyAddButton' => 'This is a test string',
                        'detail.configSubmit' => 'A new test string',
                        'detail.descriptionHeader' => 'Just another test string',
                        'documents.index_ls.DocumentIndexInvoiceID' => 'Tangled',
                        'documents.index.DocumentIndexHeadNet' => 'their dogs were astronauts',
                        'documents.index.DocumentIndexHeadNetAmount' => 'The mystery science theater 3000',
                        'documents.index.DocumentIndexHeadPosition' => 'Coincidence',
                        'footer.copyright' => 'Only possible with unit tests',
                        'footer.navigation1' => 'Who is Batman',
                        'footer.navigation2' => 'Maps of non existent Places',
                        'footer.newsletter' => 'Thank you scientist',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'detail.buyAddButton' => 'Das ist ein Test String',
                        'detail.configSubmit' => 'Ein neuer test String',
                        'detail.descriptionHeader' => 'Nur ein weiterer test String',
                        'documents.index_ls.DocumentIndexInvoiceID' => 'Verheddert',
                        'documents.index.DocumentIndexHeadNet' => 'Ihre Hunde waren Astronauten',
                        'documents.index.DocumentIndexHeadNetAmount' => 'Das Geheimnisumwitterte Wissenschaftstheater 3000',
                        'documents.index.DocumentIndexHeadPosition' => 'Zufall',
                        'footer.copyright' => 'Nur möglich mit Unititests',
                        'footer.navigation1' => 'Wer ist Batman',
                        'footer.navigation2' => 'Karten von nicht existierenden Orten',
                        'footer.newsletter' => 'Danke, Wissenschaftler',
                    ],
            ],
    ],

    // RESULT 2
    'result2' => [
        'en_GB' => [
                'snippets' => [
                        'documents.index_ls.DocumentIndexInvoiceID' => 'Tangled',
                        'documents.index.DocumentIndexHeadNet' => 'their dogs were astronauts',
                        'documents.index.DocumentIndexHeadNetAmount' => 'The mystery science theater 3000',
                        'documents.index.DocumentIndexHeadPosition' => 'Coincidence',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'documents.index_ls.DocumentIndexInvoiceID' => 'Verheddert',
                        'documents.index.DocumentIndexHeadNet' => 'Ihre Hunde waren Astronauten',
                        'documents.index.DocumentIndexHeadNetAmount' => 'Das Geheimnisumwitterte Wissenschaftstheater 3000',
                        'documents.index.DocumentIndexHeadPosition' => 'Zufall',
                    ],
            ],
    ],

    // RESULT 3
    'result3' => [
        'en_GB' => [
                'snippets' => [
                        'detail.buyAddButton' => 'This is a test string',
                        'detail.configSubmit' => 'A new test string',
                        'detail.descriptionHeader' => 'Just another test string',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'detail.buyAddButton' => 'Das ist ein Test String',
                        'detail.configSubmit' => 'Ein neuer test String',
                        'detail.descriptionHeader' => 'Nur ein weiterer test String',
                    ],
            ],
    ],

    // RESULT 4
    'result4' => [
        'en_GB' => [
                'snippets' => [
                        'detail.buyAddButton' => 'This is a test string',
                        'detail.configSubmit' => 'A new test string',
                        'detail.descriptionHeader' => 'Just another test string',
                        'footer.copyright' => 'Only possible with unit tests',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'detail.buyAddButton' => 'Das ist ein Test String',
                        'detail.configSubmit' => 'Ein neuer test String',
                        'detail.descriptionHeader' => 'Nur ein weiterer test String',
                        'footer.copyright' => 'Nur möglich mit Unititests',
                    ],
            ],
    ],

    // RESULT 5
    'result5' => [
        'en_GB' => [
                'snippets' => [
                        'footer.navigation1' => 'Who is Batman',
                    ],
            ],
        'de_DE' => [
                'snippets' => [
                        'footer.navigation1' => 'Wer ist Batman',
                    ],
            ],
    ],

    //RESULT 6
    'result6' => [
        'en_GB' => [
                'snippets' => [
                        'documents.index.DocumentIndexHeadNet' => 'their dogs were astronauts',
                    ],
            ],
    ],

    // RESULT 7
    'result7' => [
        'de_DE' => [
                'snippets' => [
                        'documents.index.DocumentIndexHeadNetAmount' => 'Das Geheimnisumwitterte Wissenschaftstheater 3000',
                    ],
            ],
    ],

    // RESULT 8
    'result8' => [
        'de_DE' => [
                'snippets' => [
                        'documents.index.DocumentIndexHeadNet' => 'Ihre Hunde waren Astronauten',
                    ],
            ],
    ],
];
