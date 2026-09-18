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

/**
 * How the rendered input component decides to show an inheritance switch:
 * - `switch-prop`: only when `isInheritanceField` is set (mt-switch).
 * - `inherited-value`: whenever `inheritedValue` is not null (mt-checkbox). sw-system-config always
 *   passes one, so those fields show the switch even while no sales channel is selected.
 * - `none`: never — the component has no inheritance support (mt-datepicker) or the field is not inheritable.
 */
type InheritanceContract = 'switch-prop' | 'inherited-value' | 'none';

type TestedFieldDefinition = FormFieldDefinition & {
    labelHelp: LabelHelpContract;
    inheritance: InheritanceContract;
};

const TESTED_FIELD_DEFINITIONS: TestedFieldDefinition[] = [
    { type: 'bool', labelHelp: 'simple', inheritance: 'switch-prop' },
    { type: 'checkbox', labelHelp: 'simple', inheritance: 'inherited-value' },
    { type: 'colorpicker', labelHelp: 'simple', inheritance: 'none' },
    { type: 'date', labelHelp: 'simple', inheritance: 'none' },
    { type: 'datetime', labelHelp: 'simple', inheritance: 'none' },
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
    { type: 'switch', labelHelp: 'simple', inheritance: 'switch-prop' },
    { type: 'tagged', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text-editor', labelHelp: 'rich', inheritance: 'none' },
    { type: 'textarea', labelHelp: 'simple', inheritance: 'none' },
    { type: 'time', labelHelp: 'simple', inheritance: 'none' },
    { type: 'url', labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-checkbox' }, labelHelp: 'simple', inheritance: 'inherited-value' },
    { type: 'text', config: { componentName: 'mt-email-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-number-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-password-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-select' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-switch' }, labelHelp: 'simple', inheritance: 'switch-prop' },
    { type: 'text', config: { componentName: 'mt-text-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-textarea' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'mt-url-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-checkbox-field' }, labelHelp: 'simple', inheritance: 'inherited-value' },
    { type: 'text', config: { componentName: 'sw-colorpicker' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-datepicker' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-email-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-number-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-password-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-price-field' }, labelHelp: 'composite', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-radio-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-select-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-snippet-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-switch-field' }, labelHelp: 'simple', inheritance: 'switch-prop' },
    { type: 'text', config: { componentName: 'sw-tagged-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-text-editor' }, labelHelp: 'rich', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-text-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-textarea-field' }, labelHelp: 'simple', inheritance: 'none' },
    { type: 'text', config: { componentName: 'sw-url-field' }, labelHelp: 'simple', inheritance: 'none' },
];

const SIMPLE_LABEL_HELP_DEFINITIONS = TESTED_FIELD_DEFINITIONS.filter((field) => field.labelHelp === 'simple');
const INHERITANCE_DEFINITIONS = TESTED_FIELD_DEFINITIONS.filter((field) => field.inheritance !== 'none');
const INHERITANCE_CONTRACTS = INHERITANCE_DEFINITIONS.map((field) => field.inheritance);

const FORM_FIELD_RENDERER_LABEL_FIELDS = buildRendererFields(removeTestMetadata(SIMPLE_LABEL_HELP_DEFINITIONS));
const FORM_FIELD_RENDERER_INHERITANCE_FIELDS = buildRendererFields(removeTestMetadata(INHERITANCE_DEFINITIONS));
const SIMPLE_LABEL_HELP_FIELDS = buildRendererFields(removeTestMetadata(SIMPLE_LABEL_HELP_DEFINITIONS));
const INHERITANCE_FIELDS = buildRendererFields(removeTestMetadata(INHERITANCE_DEFINITIONS));

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

            const wrapper = await mountCustomFieldSetRenderer(INHERITANCE_FIELDS, { hasParent });
            await flushPromises();

            INHERITANCE_FIELDS.forEach((field) => {
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

        const wrapper = await mountSystemConfig(INHERITANCE_FIELDS);
        await flushPromises();
        await switchSystemConfigToSalesChannel(wrapper);

        INHERITANCE_FIELDS.forEach((field) => {
            expectInheritanceSwitches(wrapper.find(systemConfigSelector(field)), field, 1);
        });
    });

    it('renders an inheritance switch without a sales channel only for inherited-value fields', async () => {
        expect.hasAssertions();

        const wrapper = await mountSystemConfig(INHERITANCE_FIELDS);
        await flushPromises();

        INHERITANCE_FIELDS.forEach((field, index) => {
            const expectedCount = INHERITANCE_CONTRACTS[index] === 'inherited-value' ? 1 : 0;

            expectInheritanceSwitches(wrapper.find(systemConfigSelector(field)), field, expectedCount);
        });
    });
});
