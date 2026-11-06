const { RuleTester } = require('eslint');
const { rule, tester } = require('./tester');

const CONVERTED_ALERT = '<!-- TODO Codemod: Converted from sw-alert - please check if everything works correctly -->';
const TEXT_FIELD = '<template>\n<sw-text-field value="x" />\n</template>';
const ALERT_WITH_APPEARANCE = '<template>\n<sw-alert appearance="system">Hi</sw-alert>\n</template>';
const ALERT_WITH_ACTIONS =
    '<template>\n<sw-alert><template #actions><mt-button>Retry</mt-button></template></sw-alert>\n</template>';

function atLevel(level) {
    return { 'sw-deprecation-rules': { level } };
}

function notConverted(component, intro, part) {
    return new RegExp(`^"${component}" is deprecated\\. Please use "[\\w-]+" instead\\.\\n${intro}\\n- ${part}`);
}

const NEEDS_REVIEW_OR_MANUAL = 'It is not converted, because these parts need a review or a manual migration:';
const NEEDS_MANUAL = 'It is not converted, because these parts need a manual migration first:';

tester.run('no-deprecated-components (safe level)', rule, {
    valid: [],
    invalid: [
        {
            name: 'converts an element together with its follow-up fixes and without a review comment',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: TEXT_FIELD,
            output: '<template>\n<mt-text-field model-value="x" />\n</template>',
            errors: [{ message: /^"sw-text-field" is deprecated\. Please use "mt-text-field" instead\.\n/ }],
        },
        {
            name: 'converts a select whose options move into the options prop',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-select-field v-model:value="y" label="L"><option value="a">A</option></sw-select-field>\n</template>',
            output: '<template>\n<mt-select :options="[{ value: \'a\', label: \'A\' }]" v-model="y" label="L" hide-clearable-button />\n</template>',
            errors: [{ message: /^"sw-select-field" is deprecated\. Please use "mt-select" instead\.\n/ }],
        },
        {
            name: 'keeps an element with a part that needs a review',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: ALERT_WITH_APPEARANCE,
            output: null,
            errors: [
                {
                    message: notConverted(
                        'sw-alert',
                        NEEDS_REVIEW_OR_MANUAL,
                        '\\[sw-alert\\] mt-banner has no "appearance" prop',
                    ),
                },
            ],
        },
        {
            name: 'keeps an element whose conversion itself needs a review',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-textarea-field />\n</template>',
            output: null,
            errors: [
                {
                    message: notConverted(
                        'sw-textarea-field',
                        NEEDS_REVIEW_OR_MANUAL,
                        'The conversion itself needs a review\\.',
                    ),
                },
            ],
        },
        {
            name: 'keeps an element whose start tag misses its closing bracket',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<div><sw-text-field label="L"\n</sw-text-field></div>\n</template>',
            output: null,
            errors: [
                {
                    message:
                        /^"sw-text-field" is deprecated\. .*\nIt is not converted, because its start and end tags do not pair up\.\n/,
                },
            ],
        },
        {
            name: 'converts an element that an end tag of another name closes',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<div><sw-text-field label="L"></sw-field></div>\n</template>',
            output: '<template>\n<div><mt-text-field label="L"></sw-field></div>\n</template>',
            errors: [{ message: /^"sw-text-field" is deprecated\. Please use "mt-text-field" instead\.\n/ }],
        },
        {
            name: 'keeps an element with an object v-bind, which can pass deprecated props',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-text-field v-bind="props" />\n</template>',
            output: null,
            errors: [{ message: notConverted('sw-text-field', NEEDS_REVIEW_OR_MANUAL, 'An object v-bind or v-on') }],
        },
        {
            name: 'reports a manual rename without listing its follow-up steps',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-tabs><sw-tabs-item name="a">A</sw-tabs-item></sw-tabs>\n</template>',
            output: null,
            errors: [{ message: /^"sw-tabs" is deprecated\. Please use "mt-tabs" instead\.\n(?!It is not converted)/ }],
        },
    ],
});

tester.run('no-deprecated-components (review level)', rule, {
    valid: [],
    invalid: [
        {
            name: 'puts the conversion comment in front of an element that shares its line',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: '<template>\n\t<div>Hi <sw-alert appearance="system">Hi</sw-alert></div>\n</template>',
            output: `<template>\n\t<div>Hi ${CONVERTED_ALERT} <mt-banner appearance="system">Hi</mt-banner></div>\n</template>`,
            errors: [{ message: /^"sw-alert" is deprecated\. Please use "mt-banner" instead\.\n/ }],
        },
        {
            name: 'indents the conversion comment with the tabs of the element',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: '<template>\n\t\t<sw-alert appearance="system">Hi</sw-alert>\n</template>',
            output: `<template>\n\t\t${CONVERTED_ALERT}\n\t\t<mt-banner appearance="system">Hi</mt-banner>\n</template>`,
            errors: [{ message: /^"sw-alert" is deprecated\. Please use "mt-banner" instead\.\n/ }],
        },
        {
            name: 'converts an element with a part that needs a review and leaves that part to the follow-up rule',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: ALERT_WITH_APPEARANCE,
            output: `<template>\n${CONVERTED_ALERT}\n<mt-banner appearance="system">Hi</mt-banner>\n</template>`,
            errors: [{ message: /^"sw-alert" is deprecated\. Please use "mt-banner" instead\.\n/ }],
        },
        {
            name: 'keeps an element with a part that needs a manual migration and names that part',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: ALERT_WITH_ACTIONS,
            output: null,
            errors: [{ message: notConverted('sw-alert', NEEDS_MANUAL, '\\[sw-alert\\] The "actions" slot is removed\\.') }],
        },
    ],
});

tester.run('no-deprecated-components (partial level)', rule, {
    valid: [],
    invalid: [
        {
            name: 'converts an element with a part that needs a manual migration',
            filename: 'test.html.twig',
            settings: atLevel('partial'),
            code: ALERT_WITH_ACTIONS,
            output: `<template>\n${CONVERTED_ALERT}\n<mt-banner><template #actions><mt-button>Retry</mt-button></template></mt-banner>\n</template>`,
            errors: [{ message: /^"sw-alert" is deprecated\. Please use "mt-banner" instead\.\n/ }],
        },
        {
            name: 'puts a review comment above an element whose rename is manual',
            filename: 'test.html.twig',
            settings: atLevel('partial'),
            code: '<template>\n<sw-tabs><sw-tabs-item name="a">A</sw-tabs-item></sw-tabs>\n</template>',
            output: '<template>\n<!-- TODO Codemod: "sw-tabs" is deprecated. Please use "mt-tabs" instead. -->\n<sw-tabs><sw-tabs-item name="a">A</sw-tabs-item></sw-tabs>\n</template>',
            errors: [{ message: /^"sw-tabs" is deprecated\./ }],
        },
    ],
});

tester.run('no-deprecated-components (manual level)', rule, {
    valid: [],
    invalid: [
        {
            name: 'changes no code and puts a review comment above the element',
            filename: 'test.html.twig',
            settings: atLevel('manual'),
            code: TEXT_FIELD,
            output: '<template>\n<!-- TODO Codemod: "sw-text-field" is deprecated. Please use "mt-text-field" instead. -->\n<sw-text-field value="x" />\n</template>',
            errors: [{ message: /^"sw-text-field" is deprecated\./ }],
        },
        {
            name: 'does not add the review comment twice',
            filename: 'test.html.twig',
            settings: atLevel('manual'),
            code: '<template>\n<!-- TODO Codemod: "sw-text-field" is deprecated. Please use "mt-text-field" instead. -->\n<sw-text-field value="x" />\n</template>',
            output: null,
            errors: [{ message: /^"sw-text-field" is deprecated\./ }],
        },
    ],
});

tester.run('no-deprecated-components (report only)', rule, {
    valid: [],
    invalid: [
        {
            name: 'adds no review comment when fixes are disabled, whatever the level',
            filename: 'test.html.twig',
            settings: atLevel('manual'),
            options: [{ fix: false }],
            code: TEXT_FIELD,
            output: null,
            errors: [{ message: /^"sw-text-field" is deprecated\./ }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2015 } }).run(
    'no-deprecated-components (no level setting)',
    rule,
    {
        valid: [],
        invalid: [
            {
                name: 'runs at the safe level',
                filename: 'test.html.twig',
                code: ALERT_WITH_APPEARANCE,
                output: null,
                errors: [
                    {
                        message: notConverted(
                            'sw-alert',
                            NEEDS_REVIEW_OR_MANUAL,
                            '\\[sw-alert\\] mt-banner has no "appearance" prop',
                        ),
                    },
                ],
            },
        ],
    },
);
