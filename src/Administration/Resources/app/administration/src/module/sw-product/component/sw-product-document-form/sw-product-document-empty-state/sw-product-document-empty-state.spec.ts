/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const SYSTEM_LANGUAGE_ID = 'system-language' as EntityKey<'language'>;
const GERMAN_LANGUAGE_ID = 'german' as EntityKey<'language'>;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-document-empty-state', { sync: true }), {
        slots: {
            'add-button': '<button class="add-button">Add documents</button>',
        },
        global: {
            stubs: {
                'mt-empty-state': {
                    props: ['headline', 'description'],
                    template: '<div class="mt-empty-state">{{ headline }} {{ description }}<slot name="button" /></div>',
                },
                'mt-text': { template: '<p class="mt-text"><slot /></p>' },
                'sw-product-document-language-hint': { template: '<span class="language-hint" />' },
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-document-form/sw-product-document-empty-state', () => {
    const context = Shopware.Store.get('context');
    const { languageId, systemLanguageId } = context.api;

    afterEach(() => {
        context.api.languageId = languageId;
        context.api.systemLanguageId = systemLanguageId;
    });

    it('shows the description and the add button in the default language', async () => {
        context.api.languageId = SYSTEM_LANGUAGE_ID;
        context.api.systemLanguageId = SYSTEM_LANGUAGE_ID;

        const wrapper = await createWrapper();

        expect(wrapper.text()).toContain('sw-product.documentForm.emptyDescription');
        expect(wrapper.find('.add-button').exists()).toBe(true);
        expect(wrapper.find('.language-hint').exists()).toBe(false);
    });

    it('shows the language hint instead of the add button in another language', async () => {
        context.api.languageId = GERMAN_LANGUAGE_ID;
        context.api.systemLanguageId = SYSTEM_LANGUAGE_ID;

        const wrapper = await createWrapper();

        expect(wrapper.classes()).toContain('sw-product-document-empty-state--translated');
        expect(wrapper.find('.add-button').exists()).toBe(false);
        expect(wrapper.find('.language-hint').exists()).toBe(true);
    });
});
