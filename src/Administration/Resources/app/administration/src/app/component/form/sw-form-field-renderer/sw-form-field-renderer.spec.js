/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

function createRepositoryFactoryMock() {
    const repositories = new Map();

    return {
        repositories,
        create: jest.fn((entity) => {
            if (!repositories.has(entity)) {
                repositories.set(entity, {
                    entityName: entity,
                    get: jest.fn().mockResolvedValue({ id: Shopware.Context.app.systemCurrencyId, factor: 1 }),
                });
            }

            return repositories.get(entity);
        }),
    };
}

function createStub(componentClass) {
    return {
        props: [
            'modelValue',
            'value',
            'label',
            'placeholder',
            'helpText',
            'options',
            'type',
            'numberType',
            'bordered',
            'repository',
            'error',
        ],
        template: `
            <div :class="['${componentClass}', $attrs.class]">
                <slot name="label"></slot>
                <slot></slot>
                <button class="emit-update-value" @click="$emit('update:value', '${componentClass}-legacy-update')"></button>
                <button class="emit-update-model-value" @click="$emit('update:model-value', '${componentClass}-meteor-update')"></button>
                <button class="emit-update-ids" @click="$emit('update:ids', ['first-id', 'second-id'])"></button>
                <button class="emit-update-entity-collection" @click="$emit('update:entity-collection', { entity: '${componentClass}' })"></button>
            </div>
        `,
    };
}

async function createWrapper(additionalOptions = {}, repositoryFactory = createRepositoryFactoryMock()) {
    const { global: additionalGlobal = {}, ...mountOptions } = additionalOptions;

    const defaultStubs = {
        'mt-text-field': createStub('mt-text-field'),
        'mt-number-field': createStub('mt-number-field'),
        'mt-switch': createStub('mt-switch'),
        'mt-select': createStub('mt-select'),
        'sw-entity-multi-id-select': createStub('sw-entity-multi-id-select'),
        'sw-price-field': createStub('sw-price-field'),
        'sw-contextual-field': true,
        'sw-block-field': true,
        'sw-base-field': true,
        'sw-field-error': true,
    };

    return mount(
        await wrapTestComponent('sw-form-field-renderer', {
            sync: true,
        }),
        {
            props: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
            },
            global: {
                ...additionalGlobal,
                stubs: {
                    ...defaultStubs,
                    ...(additionalGlobal.stubs || {}),
                },
                provide: {
                    validationService: {},
                    repositoryFactory,
                    ...(additionalGlobal.provide || {}),
                },
            },
            ...mountOptions,
        },
    );
}

describe('components/form/sw-form-field-renderer', () => {
    beforeAll(() => {
        global.repositoryFactoryMock.showError = false;
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                label: '<template>Label from slot</template>',
            },
        });
        await flushPromises();
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toBe('Label from slot');
    });

    it('should show the value from the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should has props error', async () => {
        const wrapper = await createWrapper({
            props: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
                error: new ShopwareError({ code: 'dummyCode' }),
            },
        });

        expect(wrapper.props().error).toBeInstanceOf(ShopwareError);
    });

    it('should init the current value when type is price without emit the update event', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'price',
                config: {
                    customFieldType: 'price',
                },
                value: undefined,
            },
        });

        expect(wrapper.vm.currentValue).toStrictEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    currencyId: null,
                    gross: null,
                    net: null,
                    linked: true,
                }),
            ]),
        );

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should not emit update:value when array values stay effectively equal', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'tagged',
                value: ['existing'],
                config: {},
            },
            global: {
                stubs: {
                    'sw-tagged-field': createStub('sw-tagged-field'),
                },
            },
        });

        wrapper.vm.currentValue = ['existing'];
        await flushPromises();

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should fetch the system currency for price fields only', async () => {
        const repositoryFactory = createRepositoryFactoryMock();

        await createWrapper(
            {
                props: {
                    type: 'price',
                    value: undefined,
                    config: {
                        customFieldType: 'price',
                    },
                },
            },
            repositoryFactory,
        );

        await flushPromises();

        expect(repositoryFactory.create).toHaveBeenCalledWith('currency');
    });

    it('should not fetch the system currency for non-price fields', async () => {
        const repositoryFactory = createRepositoryFactoryMock();

        await createWrapper(
            {
                props: {
                    type: 'string',
                    value: 'data value',
                    config: {},
                },
            },
            repositoryFactory,
        );

        await flushPromises();

        expect(repositoryFactory.create).not.toHaveBeenCalledWith('currency');
    });

    it('should fetch the system currency when switching from a non-price field to a price field', async () => {
        const repositoryFactory = createRepositoryFactoryMock();
        const wrapper = await createWrapper(
            {
                props: {
                    type: 'string',
                    value: 'data value',
                    config: {},
                },
            },
            repositoryFactory,
        );

        await flushPromises();
        expect(repositoryFactory.create).not.toHaveBeenCalledWith('currency');

        await wrapper.setProps({
            type: 'price',
            value: undefined,
            config: {
                customFieldType: 'price',
            },
        });
        await flushPromises();

        expect(repositoryFactory.create).toHaveBeenCalledWith('currency');
    });

    it.each([
        [
            'int',
            'mt-number-field',
            {
                modelValue: 'data value',
                type: 'number',
                numberType: 'int',
            },
        ],
        [
            'bool',
            'mt-switch',
            {
                modelValue: 'data value',
                type: 'switch',
                bordered: true,
            },
        ],
        [
            'string',
            'mt-text-field',
            {
                modelValue: 'data value',
                type: 'text',
            },
        ],
    ])('should resolve %s fields to %s with normalized props', async (type, componentClass, expectedProps) => {
        const wrapper = await createWrapper({
            props: {
                type,
                value: 'data value',
                config: {},
            },
        });

        await flushPromises();

        expect(wrapper.findComponent(`.${componentClass}`).props()).toEqual(expect.objectContaining(expectedProps));
    });

    it('should resolve legacy sw-field config by its custom type', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'string',
                value: 5,
                config: {
                    componentName: 'sw-field',
                    type: 'int',
                },
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.mt-number-field').exists()).toBe(true);
        expect(wrapper.findComponent('.mt-number-field').props()).toEqual(
            expect.objectContaining({
                modelValue: 5,
                type: 'int',
            }),
        );
    });

    it('should let config props override normalized type props in bind', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'int',
                value: 'data value',
                config: {
                    componentName: 'mt-number-field',
                    type: 'custom-number',
                    numberType: 'float',
                },
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.mt-number-field').props()).toEqual(
            expect.objectContaining({
                modelValue: 'data value',
                type: 'custom-number',
                numberType: 'float',
            }),
        );
    });

    it('should translate label, placeholder, and helpText from config', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'string',
                value: 'data value',
                config: {
                    label: { 'en-GB': 'Translated label' },
                    placeholder: { 'en-GB': 'Translated placeholder' },
                    helpText: { 'en-GB': 'Translated help text' },
                },
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.mt-text-field').props()).toEqual(
            expect.objectContaining({
                label: 'Translated label',
                placeholder: 'Translated placeholder',
                helpText: 'Translated help text',
            }),
        );
    });

    it('should translate select options and fall back to option value', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'single-select',
                value: 'first',
                config: {
                    options: [
                        {
                            value: 'first',
                            label: { 'en-GB': 'First option' },
                        },
                        {
                            value: 'second',
                            label: '',
                        },
                    ],
                },
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.mt-select').props('options')).toEqual([
            {
                value: 'first',
                label: 'First option',
            },
            {
                value: 'second',
                label: 'second',
            },
        ]);
    });

    it('should translate select options using a custom labelProperty', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'single-select',
                value: 'first',
                config: {
                    labelProperty: 'name',
                    options: [
                        {
                            value: 'first',
                            name: { 'en-GB': 'First option' },
                        },
                    ],
                },
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.mt-select').props('options')).toEqual([
            {
                value: 'first',
                name: 'First option',
                label: 'first',
            },
        ]);
    });

    it('should inject the repository for sw-entity-multi-id-select', async () => {
        const repositoryFactory = createRepositoryFactoryMock();
        const wrapper = await createWrapper(
            {
                props: {
                    value: [],
                    config: {
                        componentName: 'sw-entity-multi-id-select',
                        entity: 'product',
                    },
                },
            },
            repositoryFactory,
        );

        await flushPromises();

        expect(repositoryFactory.create).toHaveBeenCalledWith('product');
        expect(wrapper.findComponent('.sw-entity-multi-id-select').props('repository')).toBe(
            repositoryFactory.repositories.get('product'),
        );
    });

    it('should let special component bindings override repository values from config', async () => {
        const repositoryFactory = createRepositoryFactoryMock();
        const configRepository = { entityName: 'config-repository' };
        const wrapper = await createWrapper(
            {
                props: {
                    value: [],
                    config: {
                        componentName: 'sw-entity-multi-id-select',
                        entity: 'product',
                        repository: configRepository,
                    },
                },
            },
            repositoryFactory,
        );

        await flushPromises();

        expect(wrapper.findComponent('.sw-entity-multi-id-select').props('repository')).toBe(
            repositoryFactory.repositories.get('product'),
        );
    });

    it('should forward legacy update:value events', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'tagged',
                value: ['existing'],
                config: {},
            },
            global: {
                stubs: {
                    'sw-tagged-field': createStub('sw-tagged-field'),
                },
            },
        });

        await wrapper.find('.sw-tagged-field .emit-update-value').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:value')).toEqual([
            ['sw-tagged-field-legacy-update'],
        ]);
    });

    it('should forward Meteor update:model-value events', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'string',
                value: 'data value',
                config: {},
            },
        });

        await wrapper.find('.mt-text-field .emit-update-model-value').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:value')).toEqual([
            ['mt-text-field-meteor-update'],
        ]);
    });

    it('should forward update:ids events', async () => {
        const wrapper = await createWrapper({
            props: {
                value: [],
                config: {
                    componentName: 'sw-entity-multi-id-select',
                    entity: 'product',
                },
            },
        });

        await wrapper.find('.sw-entity-multi-id-select .emit-update-ids').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:value')).toEqual([
            [['first-id', 'second-id']],
        ]);
    });

    it('should forward update:entity-collection events', async () => {
        const wrapper = await createWrapper({
            props: {
                value: [],
                config: {
                    componentName: 'sw-entity-multi-id-select',
                    entity: 'product',
                },
            },
        });

        await wrapper.find('.sw-entity-multi-id-select .emit-update-entity-collection').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:value')).toEqual([
            [{ entity: 'sw-entity-multi-id-select' }],
        ]);
    });
});
