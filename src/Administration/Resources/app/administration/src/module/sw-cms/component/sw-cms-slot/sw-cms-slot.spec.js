import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-slot';

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-cms-slot'), {
        localVue,
        propsData: {
            element: {}
        },
        stubs: {
            'foo-bar': true,
            'sw-icon': true
        },
        provide: {
            cmsService: {
                getCmsElementConfigByName: () => ({
                    component: 'foo-bar',
                    disabledConfigInfoTextKey: 'lorem',
                    defaultConfig: {
                        text: 'lorem'
                    }
                })
            }
        }
    });
}
describe('module/sw-cms/component/sw-cms-slot', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the slot name as class', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                slot: 'left'
            }
        });

        expect(wrapper.classes()).toContain('sw-cms-slot-left');
    });

    it('disable the custom component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        expect(wrapper.classes()).toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBe('true');
    });

    it('enable the custom component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBeUndefined();
    });

    it('disable the slot setting and show tooltip when element is locked', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                locked: true
            },
            active: true
        });

        expect(wrapper.find('.sw-cms-slot__settings-action').classes()).toContain('is--disabled');
        expect(wrapper.vm.tooltipDisabled.disabled).toBe(false);
    });

    it('test onSelectElement', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.element).toEqual({});

        wrapper.vm.onSelectElement({
            name: 'testElement',
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement',
            config: {},
            data: {},
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement2',
            defaultConfig: {
                imageId: 1234567980,
            },
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement2',
            config: {
                imageId: 1234567980,
            },
            data: {},
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement3',
            defaultData: {
                text: 'Test text',
            }
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement3',
            config: {},
            data: {
                text: 'Test text',
            },
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement4',
            defaultConfig: {
                imageId: 1234567980,
            },
            defaultData: {
                text: 'Test text',
            },
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement4',
            config: {
                imageId: 1234567980,
            },
            data: {
                text: 'Test text',
            },
            locked: false,
        });
    });
});
