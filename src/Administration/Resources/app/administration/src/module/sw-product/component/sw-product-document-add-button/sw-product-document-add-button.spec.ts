/**
 * @sw-package inventory
 */
import { config, mount, shallowMount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-add-button', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;

    async function createWrapper(disabled = false, realFloatingUi = false, isInherited = false) {
        return (realFloatingUi ? mount : shallowMount)(
            await wrapTestComponent('sw-product-document-add-button', { sync: true }),
            {
                attachTo: document.body,
                props: { disabled, isInherited },
                global: {
                    provide: {
                        repositoryFactory: { create: () => ({ get: jest.fn().mockResolvedValue({ name: 'English' }) }) },
                    },
                    stubs: {
                        'sw-product-document-language-hint': await wrapTestComponent('sw-product-document-language-hint', {
                            sync: true,
                        }),
                        'i18n-t': { template: '<span><slot name="language"/></span>' },
                        'mt-floating-ui': realFloatingUi
                            ? config.global.stubs['mt-floating-ui']
                            : {
                                  props: ['isOpened'],
                                  template: '<div><slot name="trigger"/><slot v-if="isOpened"/></div>',
                              },
                    },
                },
            },
        );
    }

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('adds documents in the default language without opening a language hint', async () => {
        wrapper = await createWrapper();
        await wrapper.get('button').trigger('click');
        await wrapper.get('button').trigger('mouseenter');
        expect(wrapper.emitted('add-documents')).toEqual([[]]);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('keeps the default-language action disabled when adding is not allowed', async () => {
        wrapper = await createWrapper(true);
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
    });

    it.each([true, false])(
        'explains inherited documents instead of offering an upload or language switch (default: %s)',
        async (isDefault) => {
            if (!isDefault) {
                Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
            }
            wrapper = await createWrapper(false, false, true);
            expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
            expect(wrapper.get('button').attributes('tooltip-mock-message')).toBe('sw-product.documentForm.unlinkToAdd');
            expect(wrapper.get('button').attributes('tooltip-mock-disabled')).toBe('false');
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
            expect(wrapper.emitted('add-documents')).toBeUndefined();
        },
    );

    it('offers a language switch on focus instead of uploading in another content language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('focusin');
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain("'English'");
        await wrapper.get('button.mt-link').trigger('click');
        expect(wrapper.emitted('change-language')).toEqual([[Shopware.Context.api.systemLanguageId]]);
        expect(wrapper.emitted('add-documents')).toBeUndefined();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('keeps the hint open while the pointer moves from the button to its link and closes on leaving', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('mouseenter');
        await flushPromises();
        jest.useFakeTimers();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('mouseleave');
        await wrapper.get('[role="dialog"]').trigger('mouseenter');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        await wrapper.get('[role="dialog"]').trigger('mouseleave');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('allows keyboard users to reach the language link and return to the trigger', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        expect(document.activeElement).toBe(wrapper.get('button.mt-link').element);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(document.activeElement).toBe(wrapper.get('.sw-product-document-add-button__trigger').element);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('renders the language link in the real Meteor floating panel', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper(false, true);
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        const hint = document.querySelector('[role="dialog"]');
        expect(hint?.textContent).toContain("'English'");
        expect(document.activeElement).toBe(hint?.querySelector('button.mt-link'));
    });

    it('dismisses the hint with Escape', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('focusin');
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });
});
