import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */

const de = 'de-DE';
const en = 'en-GB';

const config = {
    label: {
        [de]: 'DeutschLabel',
        [en]: 'EnglishLabel',
    },
    translated: true,
};

const intl = {
    fallbackLocale: {
        value: en,
    },
};

const defaultProps = {
    locales: [
        en,
        de,
    ],
    config,
    propertyNames: {
        label1: 'label1',
        label2: 'label2',
    },
    disabled: false,
};

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('sw-custom-field-translated-labels', {
            sync: true,
        }),
        {
            props,
            global: {
                mocks: {
                    $i18n: intl,
                },
                stubs: {
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-ai-copilot-badge': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-help-text': true,
                    'sw-extension-component-section': true,
                    'router-link': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                default: '',
                            },
                        },
                        emits: [
                            'new-item-active',
                            'extension-item-active',
                        ],
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should render text field for single locale', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            locales: [en],
        });
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-translated-labels__single').exists()).toBe(true);
        expect(wrapper.findAll('.mt-field')).toHaveLength(2);
    });

    it.each([
        { name: 'with value', value: 'TestValue' },
        { name: 'with value', value: '' },
    ])('should update single locale text fields: $name', async ({ value }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            locales: [en],
        });
        await flushPromises();

        const textField = wrapper.find('.sw-custom-field-translated-labels__single input');
        expect(textField.exists()).toBe(true);

        await textField.setValue(value);
        await textField.trigger('update');
        await flushPromises();

        expect(wrapper.vm.config.label1[en]).toBe(value !== '' ? value : null);
    });

    it('should render multiple locales with tabs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-translated-labels__single').exists()).toBe(false);
        expect(wrapper.find('.sw-custom-field-translated-labels__tabs').exists()).toBe(true);

        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-labels-field')).toHaveLength(2);
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(
            wrapper.findAllComponents('.sw-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.en-GB)');

        await wrapper.findAll('.sw-custom-field-translated-labels__translated-labels-field')[1].trigger('click');
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(
            wrapper.findAllComponents('.sw-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.de-DE)');
    });

    it('should render multiple locales with mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-custom-field-translated-labels');
        expect(tabs.props('defaultItem')).toBe(en);
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({ label: 'locale.en-GB', name: en }),
            expect.objectContaining({ label: 'locale.de-DE', name: de }),
        ]);

        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(
            wrapper.findAllComponents('.sw-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.en-GB)');

        await tabs.vm.$emit('new-item-active', de);
        expect(wrapper.vm.currentActiveTab).toBe(de);
        expect(
            wrapper.findAllComponents('.sw-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.de-DE)');

        await tabs.vm.$emit('extension-item-active', 'extension-tab');
        expect(wrapper.vm.currentActiveTab).toBeNull();
        expect(wrapper.vm.activeTabItemName).toBe('extension-tab');
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')).toHaveLength(0);
    });

    it('should update multiple locales with tabs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const textField = wrapper.find('.sw-custom-field-translated-labels__translated-content-field input');
        expect(textField.exists()).toBe(true);

        await textField.setValue('NewValue');
        await textField.trigger('update');
        await flushPromises();

        expect(wrapper.vm.config.label1[en]).toBe('NewValue');
    });

    it('should update config when locales change and set fallback if config does not contain property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            locales: [de],
            propertyNames: {
                test: 'label1',
            },
        });
        await flushPromises();

        expect(wrapper.vm.config).toHaveProperty('test');
        expect(wrapper.vm.config.test).toStrictEqual({
            [intl.fallbackLocale.value]: null,
        });
    });
});
