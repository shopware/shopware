import { mount } from '@vue/test-utils';
import component from './index';

/**
 * @sw-package fundamentals@framework
 */
describe('src/module/sw-settings-basic-information/component/sw-settings-company-information', () => {
    async function createWrapper(props = {}) {
        return mount(component, {
            props,
            global: {
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                    'sw-entity-single-select': true,
                    'sw-media-compact-upload-v2': true,
                    'mt-text-field': true,
                    'mt-url-field': true,
                },
                mocks: {
                    $t: (key) => key,
                },
            },
        });
    }

    it('should not emit an update on initial render when using the default value', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should emit the nested company info object when a field changes', async () => {
        const wrapper = await createWrapper({
            value: {
                companyName: '',
            },
        });

        wrapper.vm.currentValue.companyName = 'ACME';
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)[0]).toEqual(
            expect.objectContaining({
                companyName: 'ACME',
            }),
        );
    });

    it('should emit the company logo id when the selection changes', async () => {
        const wrapper = await createWrapper({
            value: {
                logoId: null,
            },
        });

        await wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).vm.$emit('selection-change', [{ id: 'logo-id' }]);

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)[0]).toEqual(
            expect.objectContaining({
                logoId: 'logo-id',
            }),
        );
    });
});
