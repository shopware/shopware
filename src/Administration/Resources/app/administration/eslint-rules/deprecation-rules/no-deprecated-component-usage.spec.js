const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-component-usage');
const { loadRegistry } = require('./registry/load-registry');
const { mtIconValidTests, mtIconInvalidTests } = require('./no-deprecated-component-usage-fixtures/mt-icon.fixtures');
const {
    mtButtonValidChecks,
    mtButtonInvalidChecks,
} = require('./no-deprecated-component-usage-fixtures/mt-button.fixtures');
const { mtCardValidTests, mtCardInvalidTests } = require('./no-deprecated-component-usage-fixtures/mt-card.fixtures');
const {
    mtTextFieldValidTests,
    mtTextFieldInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-text-field.fixtures');
const {
    mtSwitchValidChecks,
    mtSwitchInvalidChecks,
} = require('./no-deprecated-component-usage-fixtures/mt-switch.fixtures');
const {
    mtNumberFieldValidTests,
    mtNumberFieldInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-number-field.fixtures');
const {
    mtCheckboxValidTests,
    mtCheckboxInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-checkbox.fixtures');
const { mtTabsValidTests, mtTabsInvalidTests } = require('./no-deprecated-component-usage-fixtures/mt-tabs.fixtures');
const { mtSelectValidTests, mtSelectInvalidTests } = require('./no-deprecated-component-usage-fixtures/mt-select.fixtures');
const {
    mtTextareaValidTests,
    mtTextareaInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-textarea.fixtures');
const { mtBannerValidTests, mtBannerInvalidTests } = require('./no-deprecated-component-usage-fixtures/mt-banner.fixtures');
const {
    mtDatepickerInvalidTests,
    mtDatepickerValidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-datepicker.fixtures');
const {
    mtColorpickerValidTests,
    mtColorpickerInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-colorpicker.fixtures');
const {
    mtEmailFieldValidTests,
    mtEmailFieldInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-email-field.fixtures');
const {
    mtPasswordFieldValidTests,
    mtPasswordFieldInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-password-field.fixtures');
const {
    mtUrlFieldValidTests,
    mtUrlFieldInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-url-field.fixtures');
const {
    mtFloatingUiValidTests,
    mtFloatingUiInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-floating-ui.fixtures');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

describe('registry helper ownership', () => {
    it('keeps component usage ESLint behavior owned by registry helpers', () => {
        const registry = loadRegistry();
        const componentLevelKinds = new Set([
            'rename-component',
            'manual-component-replacement',
        ]);
        const usagesWithoutHelperEslint = registry.componentApiMigrations.flatMap((migration) => {
            return migration.usage
                .filter((usage) => !componentLevelKinds.has(usage.kind) && !usage.eslint?.report)
                .map((usage) => `${migration.id}:${usage.kind}`);
        });

        expect(usagesWithoutHelperEslint).toEqual([]);
    });
});

function withRegistryMessageContext(cases) {
    return cases.map((testCase) => {
        return {
            ...testCase,
            errors: testCase.errors?.map((error) => {
                return {
                    ...error,
                    message: /Removed in Shopware/,
                };
            }),
        };
    });
}

const duplicateReplacementInvalidTests = [
    {
        name: 'does not fix value rename when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" prop is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix bound value rename when bound model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field :model-value="newValue" :value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" prop is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix value rename when camelCase modelValue already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field modelValue="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" prop is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix bound value rename when camelCase bound modelValue already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field :modelValue="newValue" :value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" prop is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix v-model argument rename when camelCase replacement model already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field v-model:modelValue="newValue" v-model:value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" prop is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix camelCase prop rename when replacement already exists',
        filename: 'test.html.twig',
        code: '<template><mt-switch remove-top-margin no-margin-top /></template>',
        output: null,
        errors: [
            {
                message: '[mt-switch] The "noMarginTop" prop is removed. Use "remove-top-margin" instead.',
            },
        ],
    },
    {
        name: 'does not fix event rename when replacement event already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field @update:model-value="onNew" @update:value="onOld" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "update:value" event is deprecated. Use "update:model-value" instead.',
            },
        ],
    },
];

const registryDrivenValidTests = [
    {
        name: 'registry-driven mt-external-link without icon prop is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-external-link />
            </template>`,
    },
    {
        name: 'registry-driven mt-progress-bar without deprecated value API is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar model-value="Hello World" />
            </template>`,
    },
    {
        name: 'registry-driven sw-entity-listing with data-source prop is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing
                    :data-source="entityList"
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`,
    },
];

const registryDrivenInvalidTests = [
    {
        name: 'registry-driven mt-external-link removes static icon prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-external-link icon="default-test-icon" />
            </template>`,
        output: `
            <template>
                <mt-external-link  />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" API is deprecated.',
            },
        ],
    },
    {
        name: 'registry-driven mt-external-link removes bound icon prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-external-link :icon="theIcon" />
            </template>`,
        output: `
            <template>
                <mt-external-link  />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" API is deprecated.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar renames static value prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar model-value="Hello World" />
            </template>`,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar renames bound value prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar :model-value="myValue" />
            </template>`,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar renames v-model value argument',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar v-model="myValue" />
            </template>`,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar renames update value event',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar @update:value="myValue = $event" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar @update:model-value="myValue = $event" />
            </template>`,
        errors: [
            {
                message: '[mt-progress-bar] The "update:value" API is deprecated. Use "update:model-value" instead.',
            },
        ],
    },
    {
        name: 'registry-driven sw-entity-listing renames bound items prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing :items="taxList" :repository="taxRepository" :columns="columns" />
            </template>`,
        output: `
            <template>
                <sw-entity-listing :data-source="taxList" :repository="taxRepository" :columns="columns" />
            </template>`,
        errors: [
            {
                message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar does not fix value when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-progress-bar model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'registry-driven sw-entity-listing does not fix items when data-source already exists',
        filename: 'test.html.twig',
        code: '<template><sw-entity-listing :data-source="newValue" :items="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
            },
        ],
    },
];

const objectVBindInvalidTests = [
    {
        name: 'does not crash on object v-bind while checking deprecated button variant values',
        filename: 'test.html.twig',
        code: '<template><mt-button v-bind="buttonProps" variant="ghost" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-button] The "variant" API is deprecated. Use "primary" instead.',
            },
        ],
    },
    {
        name: 'does not fix router-link when object v-bind can hide button props',
        filename: 'test.html.twig',
        code: '<template><mt-button v-bind="buttonProps" variant="primary" router-link="sw.dashboard.index" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-button] The "router-link" API is deprecated.',
            },
        ],
    },
    {
        name: 'does not fix ai-badge when object v-bind can hide card props',
        filename: 'test.html.twig',
        code: '<template><mt-card v-bind="cardProps" ai-badge /></template>',
        output: null,
        errors: [
            {
                message: '[mt-card] The "ai-badge" API is deprecated.',
            },
        ],
    },
    {
        name: 'does not fix removed props when object v-bind can hide card props',
        filename: 'test.html.twig',
        code: '<template><mt-card v-bind="cardProps" content-padding /></template>',
        output: null,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
            },
        ],
    },
    {
        name: 'does not fix renamed props when object v-bind can hide replacement props',
        filename: 'test.html.twig',
        code: '<template><mt-progress-bar v-bind="progressProps" value="50" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix show-icon inversion when object v-bind can hide banner props',
        filename: 'test.html.twig',
        code: '<template><mt-banner v-bind="bannerProps" show-icon /></template>',
        output: null,
        errors: [
            {
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.',
            },
        ],
    },
];

tester.run('no-deprecated-component-usage', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: '',
        },
        ...mtButtonValidChecks,
        ...mtIconValidTests,
        ...mtCardValidTests,
        ...mtTextFieldValidTests,
        ...mtSwitchValidChecks,
        ...mtNumberFieldValidTests,
        ...mtCheckboxValidTests,
        ...mtTabsValidTests,
        ...mtSelectValidTests,
        ...mtTextareaValidTests,
        ...mtBannerValidTests,
        ...mtDatepickerValidTests,
        ...mtColorpickerValidTests,
        ...mtEmailFieldValidTests,
        ...mtPasswordFieldValidTests,
        ...mtUrlFieldValidTests,
        ...mtFloatingUiValidTests,
        ...registryDrivenValidTests,
    ],
    invalid: withRegistryMessageContext([
        ...mtButtonInvalidChecks,
        ...mtIconInvalidTests,
        ...mtCardInvalidTests,
        ...mtTextFieldInvalidTests,
        ...mtSwitchInvalidChecks,
        ...mtNumberFieldInvalidTests,
        ...mtCheckboxInvalidTests,
        ...mtTabsInvalidTests,
        ...mtSelectInvalidTests,
        ...mtTextareaInvalidTests,
        ...mtDatepickerInvalidTests,
        ...mtBannerInvalidTests,
        ...mtColorpickerInvalidTests,
        ...mtEmailFieldInvalidTests,
        ...mtPasswordFieldInvalidTests,
        ...mtUrlFieldInvalidTests,
        ...mtFloatingUiInvalidTests,
        ...duplicateReplacementInvalidTests,
        ...registryDrivenInvalidTests,
        ...objectVBindInvalidTests,
    ]),
});
