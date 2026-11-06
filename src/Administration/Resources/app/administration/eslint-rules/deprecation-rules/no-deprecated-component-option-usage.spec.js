const path = require('path');
const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-component-option-usage');
const { getDeprecatedOptionIndex } = require('./registry/deprecated-option-index');
const { describeWithSelectedDeprecationIds } = require('./test-utils/selected-deprecation-ids');

const componentSources = path.join(__dirname, 'fixtures/no-deprecated-component-option-usage');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
    },
});

/**
 * Builds a test case that lints one template against the fixture components, which carry the
 * `deprecated` option the way Administration components will.
 *
 * @example
 * templateCase('reports …', '<sw-legacy-card />', { errors: [{ message: /…/ }] });
 */
function templateCase(name, template, expectation = {}) {
    return {
        name,
        filename: 'test.html.twig',
        code: `<template>${template}</template>`,
        options: [{ componentSources }],
        ...expectation,
    };
}

describe('deprecated option index', () => {
    it('scans the component sources once per ESLint process', () => {
        expect(getDeprecatedOptionIndex(componentSources)).toBe(getDeprecatedOptionIndex(componentSources));
    });
});

tester.run('no-deprecated-component-option-usage', rule, {
    valid: [
        templateCase('allows components without a deprecated option', '<sw-unrelated empty-icon="x" />'),
        templateCase('allows props without a deprecated option', '<sw-image placeholder-icon="x" />'),
        templateCase('leaves a component with a registry codemod to the registry rules', '<sw-alert variant="info" />'),
        templateCase('leaves a prop with a registry codemod to the registry rules', '<sw-entity-listing :items="items" />'),
        templateCase('ignores a v-model with a dynamic argument', '<sw-image v-model:[dynamic]="x" />'),
    ],
    invalid: [
        templateCase('reports a deprecated component with version and comment', '<sw-legacy-card title="x" />', {
            errors: [
                {
                    message:
                        'The component "sw-legacy-card" is deprecated and will be removed in Shopware v6.8.0.0.\nUse "mt-card" instead.',
                },
            ],
        }),
        templateCase('reports a deprecated component written in PascalCase', '<SwLegacyCard />', {
            errors: [{ message: /The component "sw-legacy-card" is deprecated/ }],
        }),
        templateCase('reports a deprecated prop passed in kebab-case', '<sw-image empty-icon="x" />', {
            errors: [
                {
                    message:
                        'The prop "emptyIcon" of the component "sw-image" is deprecated and will be removed in Shopware v6.8.0.0.\nUse "placeholderIcon" instead.',
                },
            ],
        }),
        templateCase('reports a deprecated prop passed in camelCase', '<sw-image emptyIcon="x" />', {
            errors: [{ message: /The prop "emptyIcon"/ }],
        }),
        templateCase('reports a deprecated prop bound with the shorthand', '<sw-image :empty-icon="icon" />', {
            errors: [{ message: /The prop "emptyIcon"/ }],
        }),
        templateCase('reports a deprecated prop bound with v-bind', '<sw-image v-bind:empty-icon="icon" />', {
            errors: [{ message: /The prop "emptyIcon"/ }],
        }),
        templateCase('reports a deprecated prop bound with a v-model argument', '<sw-image v-model:empty-icon="icon" />', {
            errors: [{ message: /The prop "emptyIcon"/ }],
        }),
        templateCase('reports a deprecated modelValue prop bound with a plain v-model', '<sw-image v-model="value" />', {
            errors: [{ message: /The prop "modelValue" of the component "sw-image" is deprecated/ }],
        }),
        templateCase('reports a prop deprecated with a version string only', '<sw-image empty-image-path="x" />', {
            errors: [
                {
                    message:
                        'The prop "emptyImagePath" of the component "sw-image" is deprecated and will be removed in Shopware v6.8.0.0.',
                },
            ],
        }),
        templateCase('reports each deprecated prop of one usage', '<sw-image empty-icon="x" :empty-image-path="path" />', {
            errors: [{ message: /"emptyIcon"/ }, { message: /"emptyImagePath"/ }],
        }),
    ],
});

describeWithSelectedDeprecationIds('component.sw-button', () => {
    tester.run('no-deprecated-component-option-usage with a deprecation id selected', rule, {
        valid: [
            templateCase(
                'does not report usages without a registry codemod when only selected migrations run',
                '<sw-legacy-card title="x" />',
            ),
        ],
        invalid: [],
    });
});
