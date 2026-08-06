/**
 * @sw-package framework
 */

import {
    type FormFieldDefinition,
    buildRendererFields,
    customFieldSelector,
    expectInheritanceSwitches,
    expectOneLabelAndHelpText,
    mountCustomFieldSetRenderer,
    mountFormFieldRenderer,
    mountSystemConfig,
    switchSystemConfigToSalesChannel,
    systemConfigSelector,
} from './renderer-label-help-text-inheritance.helper';

type LabelHelpContract = 'simple' | 'composite' | 'rich';
type InheritanceContract = 'component-owned' | 'wrapper-owned' | 'none';

type TestedFieldDefinition = FormFieldDefinition & {
    labelHelp: LabelHelpContract;
    inheritance: InheritanceContract;
};

const TESTED_FIELD_DEFINITIONS: TestedFieldDefinition[] = [
    { type: 'bool', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'checkbox', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'colorpicker', labelHelp: 'simple', inheritance: 'none' },
    { type: 'date', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'datetime', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'email', labelHelp: 'simple', inheritance: 'none' },
    { type: 'float', labelHelp: 'simple', inheritance: 'none' },
    { type: 'int', labelHelp: 'simple', inheritance: 'none' },
    { type: 'multi-select', labelHelp: 'simple', inheritance: 'none' },
    { type: 'number', labelHelp: 'simple', inheritance: 'none' },
    { type: 'password', labelHelp: 'simple', inheritance: 'none' },
    { type: 'price', labelHelp: 'composite', inheritance: 'none' },
    { type: 'radio', labelHelp: 'simple', inheritance: 'none' },
    { type: 'single-select', labelHelp: 'simple', inheritance: 'none' },
    { type: 'string', labelHelp: 'simple', inheritance: 'none' },
    { type: 'switch', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'tagged', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text-editor', labelHelp: 'rich', inheritance: 'none' },
    { type: 'textarea', labelHelp: 'simple', inheritance: 'none' },
    { type: 'time', labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'url', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-checkbox' }, labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'text', config: { componentName: 'mt-email-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-number-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-password-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-select' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-switch' }, labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'text', config: { componentName: 'mt-text-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-textarea' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-url-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-checkbox-field' }, labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'text', config: { componentName: 'sw-colorpicker' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-datepicker' }, labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'text', config: { componentName: 'sw-email-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-number-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-password-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-price-field' }, labelHelp: 'composite', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-radio-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-select-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-snippet-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-switch-field' }, labelHelp: 'simple', inheritance: 'component-owned' },
    { type: 'text', config: { componentName: 'sw-tagged-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-text-editor' }, labelHelp: 'rich', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-text-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-textarea-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-url-field' }, labelHelp: 'simple', inheritance: 'none' },
];

const SIMPLE_LABEL_HELP_DEFINITIONS = TESTED_FIELD_DEFINITIONS.filter((field) => field.labelHelp === 'simple');
const COMPONENT_OWNED_INHERITANCE_DEFINITIONS = TESTED_FIELD_DEFINITIONS.filter((field) => {
    return field.inheritance === 'component-owned';
});

const FORM_FIELD_RENDERER_LABEL_FIELDS = buildRendererFields(removeTestMetadata(SIMPLE_LABEL_HELP_DEFINITIONS));
const FORM_FIELD_RENDERER_INHERITANCE_FIELDS = buildRendererFields(
    removeTestMetadata(COMPONENT_OWNED_INHERITANCE_DEFINITIONS),
);
const SIMPLE_LABEL_HELP_FIELDS = buildRendererFields(removeTestMetadata(SIMPLE_LABEL_HELP_DEFINITIONS));
const COMPONENT_OWNED_INHERITANCE_FIELDS = buildRendererFields(removeTestMetadata(COMPONENT_OWNED_INHERITANCE_DEFINITIONS));

function removeTestMetadata(fields: TestedFieldDefinition[]): FormFieldDefinition[] {
    return fields.map(({ type, config }) => ({ type, config }));
}

describe('components/form/sw-form-field-renderer label, help text, and inheritance rendering', () => {
    it.each(FORM_FIELD_RENDERER_LABEL_FIELDS)(
        'renders one label and one help text for $type $config.componentName',
        async (field) => {
            expect.hasAssertions();

            const wrapper = await mountFormFieldRenderer(field);

            expectOneLabelAndHelpText(wrapper, field);
            expectInheritanceSwitches(wrapper, field, 0);
        },
    );

    it.each(FORM_FIELD_RENDERER_INHERITANCE_FIELDS)(
        'renders one inheritance switch when inheritance is enabled for $type $config.componentName',
        async (field) => {
            expect.hasAssertions();

            const wrapper = await mountFormFieldRenderer(field, {
                isInheritanceField: true,
                isInherited: true,
            });

            expectInheritanceSwitches(wrapper, field, 1);
        },
    );

    it.each(FORM_FIELD_RENDERER_INHERITANCE_FIELDS)(
        'renders no inheritance switch when inheritance is disabled for $type $config.componentName',
        async (field) => {
            expect.hasAssertions();

            const wrapper = await mountFormFieldRenderer(field, {
                isInheritanceField: false,
                isInherited: false,
            });

            expectInheritanceSwitches(wrapper, field, 0);
        },
    );
});

describe('components/form/sw-custom-field-set-renderer label, help text, and inheritance rendering', () => {
    it('renders one label and one help text for each simple custom field', async () => {
        expect.hasAssertions();

        const wrapper = await mountCustomFieldSetRenderer(SIMPLE_LABEL_HELP_FIELDS, { hasParent: false });
        await flushPromises();

        SIMPLE_LABEL_HELP_FIELDS.forEach((field) => {
            expectOneLabelAndHelpText(wrapper.find(customFieldSelector(field)), field);
        });
    });

    it.each([
        { hasParent: true, expectedCount: 1 },
        { hasParent: false, expectedCount: 0 },
    ])(
        'renders $expectedCount inheritance switch for each custom field when hasParent is $hasParent',
        async ({ hasParent, expectedCount }) => {
            expect.hasAssertions();

            const wrapper = await mountCustomFieldSetRenderer(COMPONENT_OWNED_INHERITANCE_FIELDS, { hasParent });
            await flushPromises();

            COMPONENT_OWNED_INHERITANCE_FIELDS.forEach((field) => {
                expectInheritanceSwitches(wrapper.find(customFieldSelector(field)), field, expectedCount);
            });
        },
    );
});

describe('src/module/sw-settings/component/sw-system-config label, help text, and inheritance rendering', () => {
    it('renders one label and one help text for each simple system config field', async () => {
        expect.hasAssertions();

        const wrapper = await mountSystemConfig(SIMPLE_LABEL_HELP_FIELDS);
        await flushPromises();

        SIMPLE_LABEL_HELP_FIELDS.forEach((field) => {
            expectOneLabelAndHelpText(wrapper.find(systemConfigSelector(field)), field);
        });
    });

    it('renders one inheritance switch for each system config field when a sales channel is selected', async () => {
        expect.hasAssertions();

        const wrapper = await mountSystemConfig(COMPONENT_OWNED_INHERITANCE_FIELDS);
        await flushPromises();
        await switchSystemConfigToSalesChannel(wrapper);

        COMPONENT_OWNED_INHERITANCE_FIELDS.forEach((field) => {
            expectInheritanceSwitches(wrapper.find(systemConfigSelector(field)), field, 1);
        });
    });

    it('renders no inheritance switch for each system config field without a sales channel', async () => {
        expect.hasAssertions();

        const wrapper = await mountSystemConfig(COMPONENT_OWNED_INHERITANCE_FIELDS);
        await flushPromises();

        COMPONENT_OWNED_INHERITANCE_FIELDS.forEach((field) => {
            expectInheritanceSwitches(wrapper.find(systemConfigSelector(field)), field, 0);
        });
    });
});
