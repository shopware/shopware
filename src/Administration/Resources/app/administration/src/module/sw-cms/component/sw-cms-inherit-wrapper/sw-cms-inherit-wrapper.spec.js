/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const { get } = Shopware.Utils.object;

async function createWrapper(props = {}, options = {}) {
    const defaultProps = {
        element: {
            id: 'test-slot-id',
            config: {
                testField: {
                    value: 'runtime-value',
                },
            },
            translated: {
                config: {
                    testField: {
                        value: 'base-value',
                    },
                },
            },
        },
        field: 'testField',
        ...props,
    };

    return mount(
        await wrapTestComponent('sw-cms-inherit-wrapper', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-inheritance-switch': {
                        template: `
                            <button
                                class="sw-inheritance-switch"
                                @click="$emit(isInherited ? 'inheritance-remove' : 'inheritance-restore')"
                            >
                                {{ isInherited ? 'Remove' : 'Restore' }}
                            </button>
                        `,
                        props: ['isInherited'],
                    },
                    'mt-icon': {
                        template: '<i class="mt-icon" :name="name"></i>',
                        props: ['name'],
                    },
                    'sw-confirm-modal': {
                        template: `
                            <div class="sw-confirm-modal">
                                <button class="confirm-btn" @click="$emit('confirm')">Confirm</button>
                                <button class="cancel-btn" @click="$emit('cancel')">Cancel</button>
                                <button class="close-btn" @click="$emit('close')">Close</button>
                            </div>
                        `,
                        props: ['type', 'text', 'title', 'textConfirm'],
                    },
                },
                directives: {
                    tooltip: {},
                },
            },
            props: defaultProps,
            ...options,
        },
    );
}

describe('src/module/sw-cms/component/sw-cms-inherit-wrapper', () => {
    describe('computed properties', () => {
        it('should compute fullPath correctly with default fieldPath', async () => {
            const wrapper = await createWrapper({
                field: 'backgroundColor',
            });

            expect(wrapper.vm.fullPath).toBe('backgroundColor.value');
        });

        it('should compute fullPath correctly with custom fieldPath', async () => {
            const wrapper = await createWrapper({
                field: 'backgroundColor',
                fieldPath: 'customPath',
            });

            expect(wrapper.vm.fullPath).toBe('backgroundColor.customPath');
        });

        it('should compute baseConfig from element.translated.config', async () => {
            const translatedConfig = {
                testField: { value: 'base-value' },
            };
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: {},
                    translated: {
                        config: translatedConfig,
                    },
                },
            });

            expect(wrapper.vm.baseConfig).toStrictEqual(translatedConfig);
        });

        it('should compute childConfig from contentEntity.slotConfig', async () => {
            const slotConfig = {
                'test-slot-id': {
                    testField: { value: 'child-value' },
                },
            };
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig,
                },
            });

            expect(wrapper.vm.childConfig).toStrictEqual(slotConfig['test-slot-id']);
        });

        it('should return undefined childConfig when contentEntity has no slotConfig', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            expect(wrapper.vm.childConfig).toBeUndefined();
        });

        it('should compute runtimeConfig from element.config', async () => {
            const runtimeConfig = {
                testField: { value: 'runtime-value' },
            };
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: runtimeConfig,
                    translated: { config: {} },
                },
            });

            expect(wrapper.vm.runtimeConfig).toStrictEqual(runtimeConfig);
        });

        it('should support inheritance when contentEntity is provided', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            expect(wrapper.vm.supportsInheritance).toBe(true);
        });

        it('should not support inheritance when contentEntity is null', async () => {
            const wrapper = await createWrapper({
                contentEntity: null,
            });

            expect(wrapper.vm.supportsInheritance).toBe(false);
        });

        it('should allow field inheritance when field exists in baseConfig', async () => {
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: { testField: { value: 'runtime' } },
                    translated: {
                        config: {
                            testField: { value: 'base' },
                        },
                    },
                },
            });

            expect(wrapper.vm.canInheritField).toBe(true);
        });

        it('should not allow field inheritance when field does not exist in baseConfig', async () => {
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: { testField: { value: 'runtime' } },
                    translated: {
                        config: {},
                    },
                },
            });

            expect(wrapper.vm.canInheritField).toBe(false);
        });

        it('should compute isInherited as true when contentEntity exists but field not in childConfig', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {},
                    },
                },
            });

            expect(wrapper.vm.isInherited).toBe(true);
        });

        it('should compute isInherited as false when field is overridden in childConfig', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: {
                                value: 'overridden',
                            },
                        },
                    },
                },
            });

            expect(wrapper.vm.isInherited).toBe(false);
        });

        it('should compute isInherited as falsy when contentEntity is null', async () => {
            const wrapper = await createWrapper({
                contentEntity: null,
            });

            expect(wrapper.vm.isInherited).toBeFalsy();
        });

        it('should get current value from runtimeConfig', async () => {
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: {
                        testField: {
                            value: 'runtime-value',
                        },
                    },
                    translated: { config: {} },
                },
            });

            expect(wrapper.vm.currentValue).toBe('runtime-value');
        });

        it('should set current value in runtimeConfig', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.currentValue = 'new-value';

            expect(get(wrapper.vm.runtimeConfig, wrapper.vm.fullPath)).toBe('new-value');
        });

        it('should emit update:value event when currentValue is set', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.currentValue = 'new-value';

            expect(wrapper.emitted('update:value')).toBeTruthy();
            expect(wrapper.emitted('update:value')[0]).toEqual(['new-value']);
        });

        it('should work with nested fieldPath', async () => {
            const wrapper = await createWrapper({
                element: {
                    id: 'test-id',
                    config: {
                        testField: {
                            nested: {
                                value: 'nested-value',
                            },
                        },
                    },
                    translated: { config: {} },
                },
                fieldPath: 'nested.value',
            });

            expect(wrapper.vm.currentValue).toBe('nested-value');

            wrapper.vm.currentValue = 'new-nested-value';
            expect(get(wrapper.vm.runtimeConfig, wrapper.vm.fullPath)).toBe('new-nested-value');
        });
    });

    describe('conditional rendering', () => {
        it('should render sw-inheritance-switch when inheritance is supported and field can be inherited', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            expect(wrapper.find('.sw-inheritance-switch').exists()).toBe(true);
        });

        it('should not render sw-inheritance-switch when inheritance is not supported', async () => {
            const wrapper = await createWrapper({
                contentEntity: null,
            });

            expect(wrapper.find('.sw-inheritance-switch').exists()).toBe(false);
        });

        it('should render disabled icon when field cannot be inherited', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
                element: {
                    id: 'test-id',
                    config: { testField: { value: 'runtime' } },
                    translated: {
                        config: {},
                    },
                },
            });

            expect(wrapper.find('.mt-icon').exists()).toBe(true);
            expect(wrapper.find('.sw-inheritance-switch').exists()).toBe(false);
        });

        it('should render label when label prop is provided', async () => {
            const wrapper = await createWrapper({
                label: 'Test Label',
            });

            const label = wrapper.find('label');
            expect(label.exists()).toBe(true);
            expect(label.text()).toBe('Test Label');
        });

        it('should not render label when label prop is not provided', async () => {
            const wrapper = await createWrapper({
                label: undefined,
            });

            expect(wrapper.find('label').exists()).toBe(false);
        });

        it('should render modal when showModal is true', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-confirm-modal').exists()).toBe(false);

            await wrapper.setData({ showModal: true });

            expect(wrapper.find('.sw-confirm-modal').exists()).toBe(true);
        });

        it('should apply is--inherited class when field is inherited', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {},
                    },
                },
            });

            expect(wrapper.find('.sw-cms-inherit-wrapper').classes()).toContain('is--inherited');
        });

        it('should not apply is--inherited class when field is not inherited', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
            });

            expect(wrapper.find('.sw-cms-inherit-wrapper').classes()).not.toContain('is--inherited');
        });
    });

    describe('onInheritanceRemove method', () => {
        it('should create slotConfig structure if it does not exist', async () => {
            const contentEntity = {};
            const wrapper = await createWrapper({
                contentEntity,
            });

            wrapper.vm.onInheritanceRemove();

            expect(contentEntity.slotConfig).toBeDefined();
            expect(contentEntity.slotConfig['test-slot-id']).toBeDefined();
        });

        it('should copy base value to childConfig', async () => {
            const contentEntity = {};
            const wrapper = await createWrapper({
                contentEntity,
                element: {
                    id: 'test-slot-id',
                    config: {
                        testField: { value: 'runtime' },
                    },
                    translated: {
                        config: {
                            testField: { value: 'base-value' },
                        },
                    },
                },
            });

            wrapper.vm.onInheritanceRemove();

            expect(contentEntity.slotConfig['test-slot-id'].testField.value).toBe('base-value');
        });

        it('should use default value object with null when base value does not exist', async () => {
            const contentEntity = {};
            const wrapper = await createWrapper({
                contentEntity,
                element: {
                    id: 'test-slot-id',
                    config: {
                        testField: { value: 'runtime' },
                    },
                    translated: {
                        config: {},
                    },
                },
            });

            wrapper.vm.onInheritanceRemove();

            expect(contentEntity.slotConfig['test-slot-id'].testField).toStrictEqual({
                value: { value: null },
            });
        });

        it('should emit inheritance:remove event', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            wrapper.vm.onInheritanceRemove();

            expect(wrapper.emitted('inheritance:remove')).toBeTruthy();
            expect(wrapper.emitted('inheritance:remove').length).toBe(1);
        });

        it('should do nothing if contentEntity is null', async () => {
            const wrapper = await createWrapper({
                contentEntity: null,
            });

            wrapper.vm.onInheritanceRemove();

            expect(wrapper.emitted('inheritance:remove')).toBeFalsy();
        });

        it('should work when slotConfig already exists', async () => {
            const contentEntity = {
                slotConfig: {
                    'other-slot-id': {
                        someField: { value: 'other' },
                    },
                },
            };
            const wrapper = await createWrapper({
                contentEntity,
            });

            wrapper.vm.onInheritanceRemove();

            expect(contentEntity.slotConfig['other-slot-id']).toBeDefined();
            expect(contentEntity.slotConfig['other-slot-id'].someField.value).toBe('other');
            expect(contentEntity.slotConfig['test-slot-id']).toBeDefined();
        });

        it('should work when slotConfig[element.id] already exists', async () => {
            const contentEntity = {
                slotConfig: {
                    'test-slot-id': {
                        otherField: { value: 'existing' },
                    },
                },
            };
            const wrapper = await createWrapper({
                contentEntity,
                element: {
                    id: 'test-slot-id',
                    config: {
                        testField: { value: 'runtime' },
                    },
                    translated: {
                        config: {
                            testField: { value: 'base-value' },
                        },
                    },
                },
            });

            wrapper.vm.onInheritanceRemove();

            expect(contentEntity.slotConfig['test-slot-id'].otherField).toBeDefined();
            expect(contentEntity.slotConfig['test-slot-id'].otherField.value).toBe('existing');
            expect(contentEntity.slotConfig['test-slot-id'].testField.value).toBe('base-value');
        });
    });

    describe('onInheritanceRestore method', () => {
        it('should close the modal', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
            });

            await wrapper.setData({ showModal: true });
            expect(wrapper.vm.showModal).toBe(true);

            wrapper.vm.onInheritanceRestore();

            expect(wrapper.vm.showModal).toBe(false);
        });

        it('should restore base value to runtime config', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
                element: {
                    id: 'test-slot-id',
                    config: {
                        testField: { value: 'runtime' },
                    },
                    translated: {
                        config: {
                            testField: { value: 'base-value' },
                        },
                    },
                },
            });

            wrapper.vm.onInheritanceRestore();

            expect(wrapper.vm.runtimeConfig.testField.value).toBe('base-value');
        });

        it('should remove field from childConfig', async () => {
            const contentEntity = {
                slotConfig: {
                    'test-slot-id': {
                        testField: { value: 'overridden' },
                        otherField: { value: 'other' },
                    },
                },
            };
            const wrapper = await createWrapper({
                contentEntity,
            });

            wrapper.vm.onInheritanceRestore();

            expect(contentEntity.slotConfig['test-slot-id'].testField).toBeUndefined();
            expect(contentEntity.slotConfig['test-slot-id'].otherField).toBeDefined();
        });

        it('should remove slotConfig from contentEntity when childConfig becomes empty', async () => {
            const contentEntity = {
                slotConfig: {
                    'test-slot-id': {
                        testField: { value: 'overridden' },
                    },
                },
            };
            const wrapper = await createWrapper({
                contentEntity,
            });

            wrapper.vm.onInheritanceRestore();

            expect(contentEntity.slotConfig).toBeUndefined();
        });

        it('should emit inheritance:restore event', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
            });

            wrapper.vm.onInheritanceRestore();

            expect(wrapper.emitted('inheritance:restore')).toBeTruthy();
            expect(wrapper.emitted('inheritance:restore').length).toBe(1);
        });
    });

    describe('event handling', () => {
        it('should show modal when inheritance-restore is triggered on switch', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
            });

            expect(wrapper.vm.showModal).toBe(false);

            await wrapper.find('.sw-inheritance-switch').trigger('click');

            expect(wrapper.vm.showModal).toBe(true);
        });

        it('should call onInheritanceRemove when inheritance-remove is triggered on switch', async () => {
            const contentEntity = {};
            const wrapper = await createWrapper({
                contentEntity,
            });

            await wrapper.find('.sw-inheritance-switch').trigger('click');

            expect(contentEntity.slotConfig).toBeDefined();
            expect(wrapper.emitted('inheritance:remove')).toBeTruthy();
        });

        it('should call onInheritanceRestore when modal is confirmed', async () => {
            const contentEntity = {
                slotConfig: {
                    'test-slot-id': {
                        testField: { value: 'overridden' },
                    },
                },
            };
            const wrapper = await createWrapper({
                contentEntity,
            });

            await wrapper.setData({ showModal: true });

            await wrapper.find('.confirm-btn').trigger('click');

            expect(wrapper.vm.showModal).toBe(false);
            expect(contentEntity.slotConfig).toBeUndefined();
            expect(wrapper.emitted('inheritance:restore')).toBeTruthy();
        });

        it('should hide modal when cancel is clicked', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            await wrapper.setData({ showModal: true });
            expect(wrapper.vm.showModal).toBe(true);

            await wrapper.find('.cancel-btn').trigger('click');

            expect(wrapper.vm.showModal).toBe(false);
        });

        it('should hide modal when close is clicked', async () => {
            const wrapper = await createWrapper({
                contentEntity: {},
            });

            await wrapper.setData({ showModal: true });
            expect(wrapper.vm.showModal).toBe(true);

            await wrapper.find('.close-btn').trigger('click');

            expect(wrapper.vm.showModal).toBe(false);
        });
    });

    describe('slot bindings', () => {
        it('should pass isInherited to default slot', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {},
                    },
                },
            }, {
                slots: {
                    default: `
                        <template #default="{ isInherited }">
                            <div class="slot-content" :data-inherited="isInherited">Content</div>
                        </template>
                    `,
                },
            });

            const slotContent = wrapper.find('.slot-content');
            expect(slotContent.exists()).toBe(true);
            expect(slotContent.attributes('data-inherited')).toBe('true');
        });

        it('should pass isInherited=false when field is not inherited', async () => {
            const wrapper = await createWrapper({
                contentEntity: {
                    slotConfig: {
                        'test-slot-id': {
                            testField: { value: 'overridden' },
                        },
                    },
                },
            }, {
                slots: {
                    default: `
                        <template #default="{ isInherited }">
                            <div class="slot-content" :data-inherited="isInherited">Content</div>
                        </template>
                    `,
                },
            });

            const slotContent = wrapper.find('.slot-content');
            expect(slotContent.exists()).toBe(true);
            expect(slotContent.attributes('data-inherited')).toBe('false');
        });
    });
});
