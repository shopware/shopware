/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import MtCheckbox from '@shopware-ag/meteor-component-library/dist/esm/MtCheckbox';
import MtColorpicker from '@shopware-ag/meteor-component-library/dist/esm/MtColorpicker';
import MtDatepicker from '@shopware-ag/meteor-component-library/dist/esm/MtDatepicker';
import MtEmailField from '@shopware-ag/meteor-component-library/dist/esm/MtEmailField';
import MtNumberField from '@shopware-ag/meteor-component-library/dist/esm/MtNumberField';
import MtPasswordField from '@shopware-ag/meteor-component-library/dist/esm/MtPasswordField';
import MtSelect from '@shopware-ag/meteor-component-library/dist/esm/MtSelect';
import MtSwitch from '@shopware-ag/meteor-component-library/dist/esm/MtSwitch';
import MtTextField from '@shopware-ag/meteor-component-library/dist/esm/MtTextField';
import MtTextarea from '@shopware-ag/meteor-component-library/dist/esm/MtTextarea';
import MtUnitField from '@shopware-ag/meteor-component-library/dist/esm/MtUnitField';
import MtUrlField from '@shopware-ag/meteor-component-library/dist/esm/MtUrlField';
interface FormFieldConfig {
    componentName?: string;
    type?: string;
    entity?: string;
    snippet?: string;
    currency?: { id: string; factor: number };
    label?: { 'en-GB': string };
    helpText?: { 'en-GB': string };
    options?: Array<{ id: string; name: string; value?: string }>;
}

export interface FormFieldDefinition {
    name?: string;
    type: string;
    config?: FormFieldConfig;
}

export type RendererField = FormFieldDefinition & {
    name: string;
    config: FormFieldConfig & {
        label: { 'en-GB': string };
        helpText: { 'en-GB': string };
        options: Array<{ id: string; name: string; value?: string }>;
    };
};

type TestWrapper = DOMWrapper<Element> | VueWrapper;
// `GlobalMountOptions` is not exported by @vue/test-utils, so derive the `global` option type from mount.
type MountGlobal = NonNullable<NonNullable<Parameters<typeof mount>[1]>['global']>;

type SystemConfigWrapper = VueWrapper<{
    actualConfigData: Record<string, Record<string, unknown>>;
    currentSalesChannelId: string | null;
    $nextTick: () => Promise<void>;
}>;

const HEADLESS_SALES_CHANNEL_ID = 'headless-sales-channel-id';
export function buildRendererFields(fields: FormFieldDefinition[]): RendererField[] {
    return fields.map(
        (field, index) =>
            ({
                ...field,
                name: `renderer_field_${index}`,
                config: {
                    ...(field.config ?? {}),
                    ...getAdditionalFieldConfig(field),
                    label: { 'en-GB': `Renderer label ${index}` },
                    helpText: { 'en-GB': `Renderer help text ${index}` },
                    options: [
                        { id: 'first', value: 'first', name: 'First option' },
                        { id: 'second', value: 'second', name: 'Second option' },
                    ],
                    ...(field.config?.componentName === 'sw-snippet-field' ? { snippet: 'renderer.snippet' } : {}),
                },
            }) as RendererField,
    );
}

function getAdditionalFieldConfig(field: FormFieldDefinition): Partial<FormFieldConfig> {
    const componentName = field.config?.componentName;

    if (
        field.type?.includes('entity') ||
        [
            'sw-entity-multi-id-select',
            'sw-entity-multi-select',
            'sw-entity-single-select',
        ].includes(componentName ?? '')
    ) {
        return { entity: 'product' };
    }

    if (componentName === 'sw-price-field') {
        return { currency: { id: Shopware.Context.app.systemCurrencyId ?? '', factor: 1 } };
    }

    return {};
}

async function createGlobalConfig(
    additionalStubs: Record<string, unknown> = {},
    additionalProvide: Record<string, unknown> = {},
) {
    const stubs: Record<string, unknown> = {
        ...meteorFieldComponents(),
        ...(await shopwareFieldComponents()),
        ...supportComponents(),
        'sw-help-text': await wrapTestComponent('sw-help-text', { sync: true }),
        'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
        ...additionalStubs,
    };

    return {
        directives: {
            tooltip: {},
            popover: {},
        },
        mocks: {
            $t: (key: string) => key,
        },
        renderStubDefaultSlot: true,
        stubs,
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve({ id: 'currency-id', factor: 1 }),
                    search: () => Promise.resolve([]),
                }),
            },
            validationService: {},
            mediaService: {},
            snippetSetService: {
                getCustomList: () => Promise.resolve({ total: 0, data: {} }),
            },
            ...additionalProvide,
        },
    } as unknown as MountGlobal;
}

function meteorFieldComponents() {
    return {
        'mt-checkbox': MtCheckbox,
        'mt-colorpicker': MtColorpicker,
        'mt-datepicker': MtDatepicker,
        'mt-email-field': MtEmailField,
        'mt-number-field': MtNumberField,
        'mt-password-field': MtPasswordField,
        'mt-select': MtSelect,
        'mt-switch': MtSwitch,
        'mt-text-field': MtTextField,
        'mt-textarea': MtTextarea,
        'mt-unit-field': MtUnitField,
        'mt-url-field': MtUrlField,
    };
}

async function shopwareFieldComponents() {
    return Object.fromEntries(
        await Promise.all(
            [
                'sw-checkbox-field',
                'sw-colorpicker',
                'sw-compact-colorpicker',
                'sw-datepicker',
                'sw-email-field',
                'sw-entity-multi-id-select',
                'sw-entity-multi-select',
                'sw-entity-single-select',
                'sw-media-field',
                'sw-multi-select',
                'sw-number-field',
                'sw-password-field',
                'sw-price-field',
                'sw-radio-field',
                'sw-select-field',
                'sw-single-select',
                'sw-snippet-field',
                'sw-switch-field',
                'sw-tagged-field',
                'sw-text-editor',
                'sw-text-field',
                'sw-textarea-field',
                'sw-url-field',
            ].map(async (componentName) => [
                componentName,
                await wrapTestComponent(componentName, { sync: true }),
            ]),
        ),
    ) as Record<string, unknown>;
}

function supportComponents() {
    return {
        'mt-banner': true,
        'sw-base-field': wrapShopwareBaseField(),
        'sw-block-field': wrapShopwareBlockField(),
        'mt-card': {
            template: '<section><slot></slot><slot name="toolbar"></slot></section>',
        },
        'mt-floating-ui': true,
        'mt-icon': true,
        'mt-skeleton-bar': true,
        'router-link': true,
        'sw-ai-copilot-badge': true,
        'sw-context-button': true,
        'sw-context-menu-item': true,
        'sw-contextual-field': true,
        'sw-field-copyable': true,
        'sw-field-error': true,
        'sw-highlight-text': true,
        'sw-loader': true,
        'sw-media-base-item': true,
        'sw-media-media-item': true,
        'sw-media-modal-delete': true,
        'sw-media-modal-move': true,
        'sw-media-modal-replace': true,
        'sw-media-modal-v2': true,
        'sw-media-preview-v2': true,
        'sw-media-upload-v2': true,
        'sw-pagination': true,
        'sw-popover': true,
        'sw-product-variant-info': true,
        'sw-select-base': wrapSelectBase(),
        'sw-select-result': true,
        'sw-select-result-list': true,
        'sw-select-selection-list': true,
        'sw-simple-search-field': true,
        'sw-skeleton': true,
        'sw-skeleton-bar': true,
        'sw-text-editor-toolbar': true,
        'sw-upload-listener': true,
    };
}

function createSystemConfigService(fields: RendererField[]) {
    return {
        getConfig: () =>
            Promise.resolve([
                {
                    title: { 'en-GB': 'Renderer card' },
                    elements: fields,
                },
            ]),
        getValues: (domain: string, salesChannelId: string | null) =>
            Promise.resolve(salesChannelId === null ? createDefaultFieldValueMap(fields) : {}),
    };
}

function createDefaultFieldValueMap(fields: RendererField[]) {
    return Object.fromEntries(
        fields.map((field) => [
            field.name,
            getFieldValue(field),
        ]),
    );
}

function getFieldValue(field: RendererField) {
    const componentName = field.config.componentName;

    if (
        [
            'multi-select',
            'tagged',
        ].includes(field.type) ||
        componentName === 'sw-tagged-field'
    ) {
        return [];
    }

    if (
        [
            'sw-entity-multi-id-select',
            'sw-entity-multi-select',
            'sw-multi-select',
        ].includes(componentName ?? '')
    ) {
        return [];
    }

    if (
        [
            'bool',
            'checkbox',
            'switch',
            'mt-checkbox',
            'mt-switch',
            'sw-checkbox-field',
            'sw-switch-field',
        ].includes(field.type) ||
        [
            'mt-checkbox',
            'mt-switch',
            'sw-checkbox-field',
            'sw-switch-field',
        ].includes(componentName ?? '')
    ) {
        return false;
    }

    if (
        [
            'float',
            'int',
            'number',
            'mt-number-field',
            'sw-number-field',
        ].includes(field.type) ||
        [
            'mt-number-field',
            'sw-number-field',
        ].includes(componentName ?? '')
    ) {
        return 1;
    }

    if (field.type === 'price' || componentName === 'sw-price-field') {
        return [
            {
                currencyId: Shopware.Context.app.systemCurrencyId,
                gross: 1,
                net: 1,
                linked: true,
            },
        ];
    }

    return '';
}

function fieldDebugMessage(field: RendererField, wrapper: TestWrapper) {
    return `${field.name} ${field.type} ${field.config?.componentName ?? ''}\n${wrapper.html()}`;
}

export function customFieldSelector(field: RendererField) {
    return `.sw-form-field-renderer-field__${field.name}`;
}

export function systemConfigSelector(field: RendererField) {
    return `.sw-system-config--field-${Shopware.Utils.string.kebabCase(field.name)}`;
}

export function expectOneLabelAndHelpText(wrapper: TestWrapper, field: RendererField) {
    const message = fieldDebugMessage(field, wrapper);

    expect(findFieldLabels(wrapper, field), message).toHaveLength(1);
    expect(findFieldHelpTexts(wrapper), message).toHaveLength(1);
}

export function expectInheritanceSwitches(wrapper: TestWrapper, field: RendererField, count: number) {
    expect(
        wrapper.findAll('.sw-inheritance-switch, .mt-inheritance-switch'),
        fieldDebugMessage(field, wrapper),
    ).toHaveLength(count);
}

export async function mountFormFieldRenderer(field: RendererField, props = {}) {
    ensureInnerTextSupport();

    return mount(await wrapTestComponent('sw-form-field-renderer', { sync: true }), {
        props: {
            type: field.type,
            config: field.config,
            value: getFieldValue(field),
            ...props,
        },
        global: await createGlobalConfig(),
    });
}

export async function mountCustomFieldSetRenderer(fields: RendererField[], { hasParent }: { hasParent: boolean }) {
    ensureInnerTextSupport();

    return mount(await wrapTestComponent('sw-custom-field-set-renderer', { sync: true }), {
        props: {
            sets: [
                {
                    id: 'renderer-set',
                    name: 'renderer_set',
                    config: {
                        label: { 'en-GB': 'Renderer set' },
                    },
                    customFields: fields,
                },
            ],
            entity: {
                id: 'entity-id',
                customFields: createDefaultFieldValueMap(fields),
                getEntityName: () => 'product',
            },
            parentEntity: hasParent
                ? {
                      id: 'parent-id',
                      translated: {
                          customFields: createDefaultFieldValueMap(fields),
                      },
                  }
                : null,
        },
        global: await createGlobalConfig({
            'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer', { sync: true }),
            'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
            'sw-tabs': tabsStub(),
            'sw-tabs-item': true,
        }),
    });
}

export async function mountSystemConfig(fields: RendererField[]): Promise<SystemConfigWrapper> {
    ensureInnerTextSupport();

    const wrapper = mount(await wrapTestComponent('sw-system-config', { sync: true }), {
        props: {
            domain: 'Renderer.config',
            salesChannelId: null,
            salesChannelSwitchable: false,
        },
        global: await createGlobalConfig(
            {
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer', { sync: true }),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
            },
            {
                systemConfigApiService: createSystemConfigService(fields),
            },
        ),
    });

    return wrapper as unknown as SystemConfigWrapper;
}

function tabsStub() {
    return {
        template: '<div><slot :active="defaultItem"></slot><slot name="content" :active="defaultItem"></slot></div>',
        props: ['defaultItem'],
        methods: {
            mountedComponent() {},
            setActiveItem() {},
        },
    };
}

export async function switchSystemConfigToSalesChannel(wrapper: SystemConfigWrapper) {
    wrapper.vm.actualConfigData[HEADLESS_SALES_CHANNEL_ID] = {};
    wrapper.vm.currentSalesChannelId = HEADLESS_SALES_CHANNEL_ID;

    await wrapper.vm.$nextTick();
}

function findFieldLabels(wrapper: TestWrapper, field: RendererField) {
    return wrapper.findAll('label').filter((label) => label.text().trim() === field.config.label['en-GB']);
}

function findFieldHelpTexts(wrapper: TestWrapper) {
    return wrapper.findAll('.sw-field__help-text, .sw-inherit-wrapper__help-text, .mt-help-text');
}

function ensureInnerTextSupport() {
    if ('innerText' in HTMLElement.prototype) {
        return;
    }

    Object.defineProperty(HTMLElement.prototype, 'innerText', {
        get(this: HTMLElement): string {
            return this.textContent ?? '';
        },
        set(this: HTMLElement, value: string) {
            this.textContent = value;
        },
    });
}

function wrapShopwareBaseField() {
    return {
        template: `
            <div class="sw-field" v-bind="$attrs">
                <div v-if="$attrs.label || $attrs.helpText || $attrs.isInheritanceField" class="sw-field__label">
                    <sw-inheritance-switch
                        v-if="$attrs.isInheritanceField || $attrs.mapInheritance?.isInheritField"
                        class="sw-field__inheritance-icon"
                        :is-inherited="$attrs.isInherited || $attrs.mapInheritance?.isInherited"
                    />
                    <label v-if="$attrs.label">{{ $attrs.label }}</label>
                    <sw-help-text
                        v-if="$attrs.helpText"
                        class="sw-field__help-text"
                        :text="$attrs.helpText"
                    />
                </div>
                <slot name="sw-field-input" v-bind="{ identification: 'field', error: null, disabled: false }"></slot>
                <slot name="hint"></slot>
            </div>
        `,
    };
}

function wrapShopwareBlockField() {
    return {
        template: `
            <sw-base-field class="sw-block-field" v-bind="$attrs">
                <template #sw-field-input="slotProps">
                    <slot name="sw-field-input" v-bind="slotProps"></slot>
                </template>
                <template #label>
                    <slot name="label"></slot>
                </template>
                <template #hint>
                    <slot name="hint"></slot>
                </template>
            </sw-base-field>
        `,
    };
}

function wrapSelectBase() {
    return {
        template: `
            <sw-block-field class="sw-select" v-bind="$attrs">
                <template #sw-field-input="slotProps">
                    <div class="sw-select__selection">
                        <slot name="sw-select-selection" v-bind="slotProps"></slot>
                    </div>
                    <slot name="results-list" v-bind="{ collapse: () => {} }"></slot>
                </template>
                <template #label>
                    <slot name="label"></slot>
                </template>
                <template #hint>
                    <slot name="hint"></slot>
                </template>
            </sw-block-field>
        `,
    };
}
