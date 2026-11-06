const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-template-events');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
    settings: {
        // The fixtures show the fixes that leave a review comment too; the default level is safe
        'sw-deprecation-rules': { level: 'review' },
    },
});

tester.run('no-deprecated-template-events', rule, {
    valid: [
        {
            name: 'allows already migrated event',
            filename: 'test.html.twig',
            code: '<template><sw-text-field @update:value="onInput" /></template>',
        },
        {
            name: 'allows unrelated component',
            filename: 'test.html.twig',
            code: '<template><div @input="onInput" /></template>',
        },
        {
            name: 'ignores dynamic event arguments',
            filename: 'test.html.twig',
            code: '<template><sw-text-field v-on:[eventName]="onInput" /></template>',
        },
        {
            name: 'allows change on sw-price-field, which has no replacement event',
            filename: 'test.html.twig',
            code: '<template><sw-price-field v-on:change="onChange" /></template>',
        },
        {
            name: 'allows change on sw-select-field, which mt-select still emits',
            filename: 'test.html.twig',
            code: '<template><sw-select-field @change="onChange" /></template>',
        },
    ],
    invalid: [
        {
            name: 'migrates shorthand event listener',
            filename: 'test.html.twig',
            code: '<template><sw-text-field @input="onInput" /></template>',
            output: '<template><sw-text-field @update:value="onInput" /></template>',
            errors: [
                {
                    message: /Removed in Shopware 6\.6\.0/,
                },
            ],
        },
        {
            name: 'migrates a kebab-case listener of a kebab-case registry event',
            filename: 'test.html.twig',
            code: '<template><sw-media-breadcrumbs @media-folder-change="onChange" /></template>',
            output: '<template><sw-media-breadcrumbs @update:currentFolderId="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:currentFolderId" instead/,
                },
            ],
        },
        {
            name: 'migrates a camelCase listener of a kebab-case registry event the same way',
            filename: 'test.html.twig',
            code: '<template><sw-media-breadcrumbs @mediaFolderChange="onChange" /></template>',
            output: '<template><sw-media-breadcrumbs @update:currentFolderId="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:currentFolderId" instead/,
                },
            ],
        },
        {
            name: 'migrates v-on event listener',
            filename: 'test.html.twig',
            code: '<template><sw-single-select v-on:change="onChange" /></template>',
            output: '<template><sw-single-select v-on:update:value="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:value" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-entity-multi-id-select change to the emitted update:value',
            filename: 'test.html.twig',
            code: '<template><sw-entity-multi-id-select @change="onChange" /></template>',
            output: '<template><sw-entity-multi-id-select @update:value="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:value" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-radio-panel input to the emitted update:modelValue',
            filename: 'test.html.twig',
            code: '<template><sw-radio-panel @input="onSelect" /></template>',
            output: '<template><sw-radio-panel @update:modelValue="onSelect" /></template>',
            errors: [
                {
                    message: /Use "update:modelValue" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-textarea-field input to the emitted update:model-value',
            filename: 'test.html.twig',
            code: '<template><sw-textarea-field @input="onInput" /></template>',
            output: '<template><sw-textarea-field @update:model-value="onInput" /></template>',
            errors: [
                {
                    message: /Use "update:model-value" instead/,
                },
            ],
        },
        {
            name: 'can disable fixer',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: '<template><sw-media-library @media-selection-change="onChange" /></template>',
            output: null,
            errors: [
                {
                    message: /Use "update:selection" instead/,
                },
            ],
        },
        {
            name: 'does not fix when the element already listens to the replacement event',
            filename: 'test.html.twig',
            code: '<template><sw-single-select @change="onChange" @update:value="onUpdate" /></template>',
            output: null,
            errors: [
                {
                    message: /already listens to "update:value" with another handler\. This listener does not fire anymore/,
                },
            ],
        },
        {
            name: 'does not fix when the element already listens to a camelCase replacement event',
            filename: 'test.html.twig',
            code: '<template><sw-radio-panel @input="a" @update:modelValue="b" /></template>',
            output: null,
            errors: [
                {
                    message: /already listens to "update:modelValue" with another handler\. This listener does not fire anymore/,
                },
            ],
        },
        {
            name: 'does not fix when the element already listens to a kebab-case replacement event',
            filename: 'test.html.twig',
            code: '<template><sw-radio-panel @input="a" @update:model-value="b" /></template>',
            output: null,
            errors: [
                {
                    message: /already listens to "update:modelValue" with another handler/,
                },
            ],
        },
        {
            name: 'removes the deprecated listener when the replacement listener runs the same handler',
            filename: 'test.html.twig',
            code: '<template>\n<sw-single-select\n    @change="onChange"\n    @update:value="onChange"\n/>\n</template>',
            output: '<template>\n<sw-single-select\n    @update:value="onChange"\n/>\n</template>',
            errors: [
                {
                    message: /already listens to "update:value" with the same handler, so this listener is removed/,
                },
            ],
        },
        {
            name: 'renames the listener of the other branch of a Twig condition',
            filename: 'test.html.twig',
            code: '<template><sw-single-select {% if VUE2 %}@change="onChange"{% else %}@update:value="onChange"{% endif %} /></template>',
            output: '<template><sw-single-select {% if VUE2 %}@update:value="onChange"{% else %}@update:value="onChange"{% endif %} /></template>',
            errors: [
                {
                    message: /Use "update:value" instead/,
                },
            ],
        },
        {
            name: 'does not fix when object v-on can hide deprecated listeners',
            filename: 'test.html.twig',
            code: '<template><sw-text-field v-on="listeners" @input="onInput" /></template>',
            output: null,
            errors: [
                {
                    message: /Object v-on can hide the replacement event/,
                },
            ],
        },
    ],
});

describe('no-deprecated-template-events with fix levels', () => {
    let fixLevelRule;

    jest.isolateModules(() => {
        jest.doMock('./registry/load-registry', () => {
            const actual = jest.requireActual('./registry/load-registry');

            return {
                loadRegistry: () => ({
                    ...actual.loadRegistry(),
                    templateEventMigrations: [
                        {
                            id: 'template-event.test-fix-levels',
                            deprecatedIn: '6.7.0',
                            removedIn: '6.8.0',
                            description: 'Test migration.',
                            references: [],
                            usage: [
                                {
                                    component: 'sw-test-manual',
                                    from: 'change',
                                    to: 'update:value',
                                    fix: 'manual',
                                    message: 'The payload changed. Rename the listener and adapt the handler.',
                                },
                                {
                                    component: 'sw-test-unsafe',
                                    from: 'change',
                                    to: 'update:value',
                                    fix: 'unsafe-auto',
                                    message: 'Check that the handler accepts the new payload.',
                                },
                                {
                                    component: 'sw-test-auto',
                                    from: 'change',
                                    to: 'update:value',
                                    fix: 'auto',
                                },
                            ],
                        },
                    ],
                }),
            };
        });

        fixLevelRule = require('./no-deprecated-template-events');
    });

    tester.run('no-deprecated-template-events', fixLevelRule, {
        valid: [],
        invalid: [
            {
                name: 'reports a manual usage with its hint and does not fix it',
                filename: 'test.html.twig',
                code: '<template><sw-test-manual @change="onChange" /></template>',
                output: null,
                errors: [
                    {
                        message: /The payload changed\. Rename the listener and adapt the handler\./,
                    },
                ],
            },
            {
                name: 'renames an unsafe-auto usage and adds a TODO Codemod review hint',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-test-unsafe @change="onChange" />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Check that the handler accepts the new payload. -->
    <sw-test-unsafe @update:value="onChange" />
</template>`,
                errors: [
                    {
                        message: /Check that the handler accepts the new payload\./,
                    },
                ],
            },
            {
                name: 'renames an auto usage without a review hint',
                filename: 'test.html.twig',
                code: '<template><sw-test-auto @change="onChange" /></template>',
                output: '<template><sw-test-auto @update:value="onChange" /></template>',
                errors: [
                    {
                        message: /Use "update:value" instead/,
                    },
                ],
            },
        ],
    });
});
