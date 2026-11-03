/**
 * @sw-package inventory
 */
import { shallowMount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';

async function createWrapper(props = {}) {
    return shallowMount(await wrapTestComponent('sw-product-document-remove-modal', { sync: true }), {
        props: { documentTitle: 'Safety certificate', canEdit: true, ...props },
        global: {
            stubs: {
                'mt-modal-root': { name: 'ModalRootStub', template: '<div><slot /></div>' },
                'mt-modal': {
                    props: ['title'],
                    template: '<section><h2>{{ title }}</h2><slot /><slot name="footer" /></section>',
                },
            },
            mocks: { $t: (key: string, values?: { name: string }) => (values?.name ? `${key}: ${values.name}` : key) },
        },
    });
}

describe('sw-product-document-remove-modal', () => {
    it('identifies the document and emits confirmation only after clicking Remove', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.get('h2').text()).toContain('Safety certificate');
        expect(wrapper.emitted('remove')).toBeUndefined();

        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');

        expect(wrapper.emitted('remove')).toHaveLength(1);
        expect(wrapper.emitted('close')).toBeUndefined();
    });

    it('emits close on Cancel without confirming removal', async () => {
        const wrapper = await createWrapper();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'global.default.cancel')!
            .trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(wrapper.emitted('remove')).toBeUndefined();
    });

    it('closes when the modal is dismissed without confirming removal', async () => {
        const wrapper = await createWrapper();
        wrapper.getComponent<ComponentPublicInstance>({ name: 'ModalRootStub' }).vm.$emit('change', false);

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(wrapper.emitted('remove')).toBeUndefined();
    });

    it.each([{ canEdit: false }, { isRemovingDocument: true }])('disables confirmation when %o', async (props) => {
        const wrapper = await createWrapper(props);
        const remove = wrapper.get('.sw-product-document-form__confirm-remove');
        expect(remove.attributes('disabled')).toBeDefined();

        await remove.trigger('click');

        expect(wrapper.emitted('remove')).toBeUndefined();
    });
});
