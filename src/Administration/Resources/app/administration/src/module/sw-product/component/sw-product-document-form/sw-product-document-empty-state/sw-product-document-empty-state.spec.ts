/** @sw-package inventory */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
describe('sw-product-document-empty-state', () => {
    let wrapper: VueWrapper;
    const originalLanguage = Shopware.Context.api.languageId;
    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguage;
    });
    it('offers document uploads in the default language', async () => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        wrapper = shallowMount(await wrapTestComponent('sw-product-document-empty-state', { sync: true }), {
            props: { canAddDocuments: true },
            global: { stubs: { 'mt-empty-state': { template: '<div><slot name="button"/></div>' } } },
        });
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('documents-add')).toHaveLength(1);
    });
});
