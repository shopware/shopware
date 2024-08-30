/**
 * @package admin
 */
import 'src/app/mixin/form-field.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        {
            template: `
                <div class="sw-mock">
                    <slot></slot>
                </div>
            `,
            mixins: [
                Shopware.Mixin.getByName('sw-form-field'),
            ],
        },
        {
            attachTo: document.body,
            props: {
                name: 'sw-mock-field',
            },
        },
    );
}

function skipIfCompatModeIsDisabled() {
    return process.env.DISABLE_JEST_COMPAT_MODE === 'true' ? it.skip : it;
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
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the correct formFieldName when this.name exists', () => {
        expect(wrapper.vm.formFieldName).toBe('sw-mock-field');
    });

    // These events are no longer bound automatically therefore the test is skipped with compat disabled
    /* eslint-disable jest/no-standalone-expect */
    skipIfCompatModeIsDisabled()('should handle the map inheritance correctly (restoreInheritance)', async () => {
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

    // These events are no longer bound automatically therefore the test is skipped with compat disabled
    skipIfCompatModeIsDisabled()('should handle the map inheritance correctly (removeInheritance)', async () => {
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
    /* eslint-enable jest/no-standalone-expect */

    it('should handle the map inheritance correctly (isInherited)', async () => {
        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: false,
                isInheritField: true,
            },
        });

        expect(wrapper.vm.inheritanceAttrs).toEqual({
            isInherited: false,
            isInheritanceField: true,
        });

        await wrapper.setProps({
            mapInheritance: {
                restoreInheritance: jest.fn(() => {}),
                removeInheritance: jest.fn(() => {}),
                isInherited: true,
                isInheritField: true,
            },
        });

        expect(wrapper.vm.inheritanceAttrs).toEqual({
            isInherited: true,
            isInheritanceField: true,
        });
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
        expect(wrapper.vm.inheritanceAttrs).toEqual({
            isInheritanceField: true,
        });
    });
});
