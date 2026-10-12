const RuleTester = require('eslint').RuleTester;
const { withRegistryMessageContext } = require('./registry/with-registry-message-context');
const { describeWithSelectedDeprecationIds } = require('./registry/selected-deprecation-ids');
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

const {
    mtProgressBarValidTests,
    mtProgressBarInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/mt-progress-bar.fixtures');
const {
    swEntityListingValidTests,
    swEntityListingInvalidTests,
} = require('./no-deprecated-component-usage-fixtures/sw-entity-listing.fixtures');

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

const duplicateReplacementInvalidTests = [
    {
        name: 'does not fix value rename when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
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
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
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
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
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
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
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
                message: '[mt-text-field] The "value" API is deprecated.',
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
                message: '[mt-switch] The "no-margin-top" API is deprecated. Use "remove-top-margin" instead.',
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
                message: '[mt-text-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
            },
        ],
    },
];

const registryDrivenValidTests = [
    {
        name: 'registry-driven mt-empty-state converted from sw-empty-state keeps a bound icon',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
                <mt-empty-state headline="T" description="D" :icon="icon" />
            </template>`,
    },
    {
        name: 'registry-driven mt-checkbox converted from sw-checkbox-field keeps its label slot',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox>
                    <template #label>Accept the <a href="#">terms</a></template>
                </mt-checkbox>
            </template>`,
    },
    {
        name: 'registry-driven mt-checkbox keeps its native label slot',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template #label>Accept</template>
                </mt-checkbox>
            </template>`,
    },
];

const registryDrivenInvalidTests = [
    {
        name: 'registry-driven mt-empty-state converted from sw-empty-state gets the route module icon and a review hint',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
                <mt-empty-state headline="T" description="D" />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
                <!-- TODO Codemod: sw-empty-state took its icon from the route module; check the icon -->
                <mt-empty-state headline="T" description="D" :icon="$route.meta.$module.icon" />
            </template>`,
        errors: [
            {
                message: '[mt-empty-state] Add an icon because mt-empty-state requires one.',
            },
        ],
    },
    {
        name: 'registry-driven mt-empty-state reports the actions slot without changing it',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-empty-state headline="T" description="D" icon="regular-products">
                    <template #actions>
                        <a href="#">Add</a>
                    </template>
                </mt-empty-state>
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-empty-state] The "actions" slot is not supported by mt-empty-state. Use the "button" slot, or the button-text prop with @button-click, or link-text with link-href.',
            },
        ],
    },
    {
        name: 'registry-driven mt-empty-state reports props mt-empty-state does not support',
        filename: 'test.html.twig',
        code: '<template><mt-empty-state headline="T" description="D" icon="regular-products" absolute :empty-module="true" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-empty-state] The "absolute" prop is not supported by mt-empty-state. Remove it and check the layout.',
            },
            {
                message: '[mt-empty-state] The "empty-module" prop is not supported by mt-empty-state. Remove it and check the layout.',
            },
        ],
    },
    {
        name: 'registry-driven mt-empty-state renames title to headline and subline to description',
        filename: 'test.html.twig',
        code: '<template><mt-empty-state title="T" subline="S" /></template>',
        output: '<template><mt-empty-state headline="T" description="S" /></template>',
        errors: [
            {
                message: '[mt-empty-state] The "title" API is deprecated. Use "headline" instead.',
            },
            {
                message: '[mt-empty-state] The "subline" API is deprecated. Use "description" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-empty-state renames bound title to headline and bound subline to description',
        filename: 'test.html.twig',
        code: '<template><mt-empty-state :title="title" :subline="subline" /></template>',
        output: '<template><mt-empty-state :headline="title" :description="subline" /></template>',
        errors: [
            {
                message: '[mt-empty-state] The "title" API is deprecated. Use "headline" instead.',
            },
            {
                message: '[mt-empty-state] The "subline" API is deprecated. Use "description" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-text-field keeps v-model modifiers when v-model:value becomes v-model',
        filename: 'test.html.twig',
        code: '<template><mt-text-field v-model:value.lazy.trim="myValue" /></template>',
        output: '<template><mt-text-field v-model.lazy.trim="myValue" /></template>',
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated.',
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
                message:
                    '[mt-button] Replace router-link with an explicit click handler or router-link wrapper and verify navigation semantics.\nObject v-bind can hide router-link usage. Review the bound object and migrate navigation manually.',
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
                message:
                    '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.\nObject v-bind can hide ai-badge usage. Review the bound object and add the title slot manually if needed.',
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
        ...mtProgressBarValidTests,
        ...swEntityListingValidTests,
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
        ...mtProgressBarInvalidTests,
        ...swEntityListingInvalidTests,
        ...duplicateReplacementInvalidTests,
        ...registryDrivenInvalidTests,
        ...objectVBindInvalidTests,
    ]),
});

describeWithSelectedDeprecationIds('component.sw-alert', () => {
    tester.run('no-deprecated-component-usage with an unrelated deprecation id selected', rule, {
        valid: [
            {
                name: '"mt-text-field" value prop is not reported when only another migration is selected',
                filename: 'test.html.twig',
                code: '<template><mt-text-field value="old" /></template>',
            },
        ],
        invalid: [],
    });
});
