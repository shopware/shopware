/**
 * @sw-package framework
 */

const path = require('path');
const { Linter } = require('eslint');
const { rule, tester } = require('./tester');
const usageRule = require('../no-deprecated-component-usage');

const SAVE_REVIEW =
    'sw-entity-many-to-many-select saved every change of the assignment without local-mode, sw-entity-multi-select never does: save the entity collection together with its parent entity';

function atLevel(level) {
    return { 'sw-deprecation-rules': { level } };
}

tester.run('no-deprecated-components (components removed in 6.8)', rule, {
    valid: [],
    invalid: [
        {
            name: 'converts sw-select-number-field with its options as numbers',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-select-number-field v-model:value="count" label="Count"><option value="1">One</option><option value="2">Two</option></sw-select-number-field>\n</template>',
            output: '<template>\n<mt-select :options="[{ value: 1, label: \'One\' }, { value: 2, label: \'Two\' }]" v-model="count" label="Count" hide-clearable-button />\n</template>',
            errors: [{ message: /^"sw-select-number-field" is deprecated\. Please use "mt-select" instead\.\n/ }],
        },
        {
            name: 'keeps sw-select-number-field with an option that is no number',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: '<template>\n<sw-select-number-field v-model:value="count"><option value="all">All</option></sw-select-number-field>\n</template>',
            output: null,
            errors: [
                {
                    message:
                        /It is not converted, because these parts need a manual migration first:\n- \[sw-select-number-field\] The "default" slot is removed\. Pass its <option> elements as the "options" prop\.\nA <option> element has the value "all", which is no number\./,
                },
            ],
        },
        {
            name: 'converts sw-entity-many-to-many-select in local mode',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-entity-many-to-many-select v-model:entity-collection="tags" local-mode />\n</template>',
            output: '<template>\n<sw-entity-multi-select v-model:entity-collection="tags" />\n</template>',
            errors: [
                {
                    message:
                        /^"sw-entity-many-to-many-select" is deprecated\. Please use "sw-entity-multi-select" instead\.\n/,
                },
            ],
        },
        {
            name: 'keeps sw-entity-many-to-many-select that saved on its own at the safe level',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-entity-many-to-many-select v-model:entity-collection="tags" />\n</template>',
            output: null,
            errors: [
                {
                    message:
                        /It is not converted, because these parts need a review or a manual migration:\n- \[sw-entity-many-to-many-select\] The "local-mode" prop is not set\.\nsw-entity-many-to-many-select saved every change/,
                },
            ],
        },
        {
            name: 'converts sw-entity-many-to-many-select that saved on its own with a review note at the review level',
            filename: 'test.html.twig',
            settings: atLevel('review'),
            code: '<template>\n<sw-entity-many-to-many-select v-model:entity-collection="tags" />\n</template>',
            output: `<template>\n<!-- TODO Codemod: Converted from sw-entity-many-to-many-select - please check if everything works correctly. ${SAVE_REVIEW} -->\n<sw-entity-multi-select v-model:entity-collection="tags" />\n</template>`,
            errors: [{ message: /^"sw-entity-many-to-many-select" is deprecated\./ }],
        },
        {
            name: 'converts sw-progress-bar with the old max-value default',
            filename: 'test.html.twig',
            settings: atLevel('safe'),
            code: '<template>\n<sw-progress-bar :value="progress" />\n</template>',
            output: '<template>\n<mt-progress-bar :model-value="progress" :max-value="100" />\n</template>',
            errors: [{ message: /^"sw-progress-bar" is deprecated\. Please use "mt-progress-bar" instead\.\n/ }],
        },
    ],
});

describe('sw-select-number-field conversion with every component rule', () => {
    const linter = new Linter({ configType: 'flat' });

    function migrate(code, level) {
        const config = [
            {
                files: ['**/*.html.twig'],
                plugins: {
                    'sw-deprecation-rules': {
                        rules: {
                            'no-deprecated-components': rule,
                            'no-deprecated-component-usage': usageRule,
                        },
                    },
                },
                settings: atLevel(level),
                languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 },
                rules: {
                    'sw-deprecation-rules/no-deprecated-components': 'error',
                    'sw-deprecation-rules/no-deprecated-component-usage': 'error',
                },
            },
        ];

        return linter.verifyAndFix(code, config, { filename: path.join(__dirname, 'test.html.twig') });
    }

    const code =
        '<template>\n<sw-select-number-field v-model:value="count"><option value="1">One</option><option value="2">Two</option></sw-select-number-field>\n</template>';
    const numberOptions =
        '<mt-select :options="[{ value: 1, label: \'One\' }, { value: 2, label: \'Two\' }]" v-model="count"';

    it('keeps the number values at the safe level, although sw-select-field moves options of every mt-select', () => {
        const { output, messages } = migrate(code, 'safe');

        expect(messages).toEqual([]);
        expect(output).toBe(`<template>\n${numberOptions} hide-clearable-button />\n</template>`);
    });

    it('keeps the number values at the review level, where the steps run after the conversion', () => {
        const { output, messages } = migrate(code, 'review');

        expect(messages).toEqual([]);
        expect(output).toBe(
            '<template>\n<!-- TODO Codemod: Converted from sw-select-number-field - please check if everything works correctly -->\n' +
                `${numberOptions} hide-clearable-button />\n</template>`,
        );
    });
});
