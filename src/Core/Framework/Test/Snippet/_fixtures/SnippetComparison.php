<?php declare(strict_types=1);

return [
    // SET 1
    'set1' => [
        'en_GB' => [
            'snippets' => [
                't_key_1' => '1',
                't_key_2' => '2',
                't_key_3' => '3',
                't_key_4' => '4',
                't_key_5' => '5',
            ],
        ],
        'en_US' => [
            'snippets' => [
                't_key_1' => '6',
                't_key_2' => '7',
                't_key_3' => '8',
                't_key_4' => '9',
                't_key_5' => '10',
            ],
        ],
    ],
    'result1' => [
        't_key_1' => [
            0 => '1',
            1 => '6',
        ],
        't_key_2' => [
            0 => '2',
            1 => '7',
        ],
        't_key_3' => [
            0 => '3',
            1 => '8',
        ],
        't_key_4' => [
            0 => '4',
            1 => '9',
        ],
        't_key_5' => [
            0 => '5',
            1 => '10',
        ],
    ],

    // SET 2
    'set2' => [
        'en_GB' => [
            'snippets' => [
                't_key_1' => [
                    'id' => '1',
                    'value' => 'FOO',
                ],
                't_key_2' => [
                    'id' => '2',
                    'value' => 'BAR',
                ],
                't_key_3' => [
                    'id' => '3',
                    'value' => 'FOOBAR',
                ],
            ],
        ],
        'en_US' => [
            'snippets' => [
                't_key_1' => [
                    'id' => '1',
                    'value' => 'foo',
                ],
                't_key_2' => [
                    'id' => '2',
                    'value' => 'bar',
                ],
                't_key_3' => [
                    'id' => '3',
                    'value' => 'foobar',
                ],
            ],
        ],
    ],
    'result2' => [
        't_key_1' => [
            0 => [
                'id' => '1',
                'value' => 'FOO',
            ],
            1 => [
                'id' => '1',
                'value' => 'foo',
            ],
        ],
        't_key_2' => [
            0 => [
                'id' => '2',
                'value' => 'BAR',
            ],
            1 => [
                'id' => '2',
                'value' => 'bar',
            ],
        ],
        't_key_3' => [
            0 => [
                'id' => '3',
                'value' => 'FOOBAR',
            ],
            1 => [
                'id' => '3',
                'value' => 'foobar',
            ],
        ],
    ],
];
