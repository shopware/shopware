import 'src/app/mixin/form-field.mixin';
import { shallowMount } from '@vue/test-utils_v2';

async function createWrapper() {
    return shallowMount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('sw-form-field'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
            };
        },
    }, {
        stubs: {},
        mocks: {},
        attachTo: document.body,
    });
}

describe('src/app/mixin/form-field.mixin.ts', () => {
    /* @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the correct formFieldName when this.name exists', () => {
        expect(wrapper.vm.formFieldName).toBe('sw-mock-field');
    });

    it('should contain the correct formFieldName when this.$attrs.name exists', async () => {
        await wrapper.setData({
            name: null,
        });
        wrapper.vm.$attrs.name = 'sw-mock-attrs-field';

        expect(wrapper.vm.formFieldName).toBe('sw-mock-attrs-field');
    });

    it('should handle the map inheritance correctly (restoreInheritance)', async () => {
        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: false,
                isInheritField: true,
            },
        });

        expect(wrapper.vm.mapInheritance.restoreInheritance).not.toHaveBeenCalled();

        wrapper.vm.$emit('inheritance-restore', {
            name: 'my-cool-product',
        });
        await flushPromises();

        expect(wrapper.vm.mapInheritance.restoreInheritance).toHaveBeenCalledWith({
            name: 'my-cool-product',
        });
    });

    it('should handle the map inheritance correctly (removeInheritance)', async () => {
        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: false,
                isInheritField: true,
            },
        });

        expect(wrapper.vm.mapInheritance.removeInheritance).not.toHaveBeenCalled();

        wrapper.vm.$emit('inheritance-remove', {
            name: 'my-cool-product',
        });
        await flushPromises();

        expect(wrapper.vm.mapInheritance.removeInheritance).toHaveBeenCalledWith({
            name: 'my-cool-product',
        });
    });

    it('should handle the map inheritance correctly (isInherited)', async () => {
        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: false,
                isInheritField: true,
            },
        });

        expect(wrapper.vm.$attrs.isInherited).toBe(false);
        expect(wrapper.vm.$attrs.isInheritanceField).toBe(true);

        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: false,
                isInheritField: false,
            },
        });

        // values should be undefined when it is no inheritance field
        expect(wrapper.vm.$attrs.isInherited).toBeUndefined();
        expect(wrapper.vm.$attrs.isInheritanceField).toBeUndefined();
    });

    it('should not handle anything when mapInheritance other props does not match the values', async () => {
        await wrapper.setProps({
            mapInheritance: {
                isInheritField: true,
                notExisting: true,
                unmatchingProperty: false,
                shop: () => 'ware',
            },
        });

        // values should be undefined when it is no inheritance field
        expect(wrapper.vm.$attrs.isInheritanceField).toBe(true);
        expect(wrapper.vm.$attrs.isInherited).toBeUndefined();
    });
});
