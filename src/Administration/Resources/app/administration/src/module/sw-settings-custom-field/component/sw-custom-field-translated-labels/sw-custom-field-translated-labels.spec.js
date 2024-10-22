import { mount } from '@vue/test-utils';

/**
 * @package services-settings
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
    locale: en,
    fallbackLocale: en,
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
            provide: {
                $root: {
                    $i18n: intl,
                },
            },
            global: {
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
                    'sw-icon': true,
                    'sw-extension-component-section': true,
                    'router-link': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels', () => {
    it('should render text field for single locale', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            locales: [en],
        });
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-translated-labels__single').exists()).toBe(true);
        expect(wrapper.findAll('.sw-field')).toHaveLength(2);
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
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')[0].attributes('label')).toBe(
            'label1 (locale.en-GB)',
        );

        await wrapper.findAll('.sw-custom-field-translated-labels__translated-labels-field')[1].trigger('click');
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(wrapper.findAll('.sw-custom-field-translated-labels__translated-content-field')[0].attributes('label')).toBe(
            'label1 (locale.de-DE)',
        );
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
            [intl.fallbackLocale]: null,
        });
    });
});
