/**
 * @sw-package inventory
 */
import { config, mount, shallowMount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-add-button', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const originalSystemLanguage = Shopware.Context.api.systemLanguageId;
    let wrapper: VueWrapper;
    let emitEvent: jest.SpyInstance;
    const getLanguage = jest.fn();

    async function createWrapper(disabled = false, useRealFloatingUi = false, isInherited = false) {
        return (useRealFloatingUi ? mount : shallowMount)(
            await wrapTestComponent('sw-product-document-add-button', { sync: true }),
            {
                attachTo: document.body,
                props: { disabled, isInherited },
                global: {
                    provide: {
                        repositoryFactory: { create: () => ({ get: getLanguage }) },
                    },
                    stubs: {
                        'sw-product-document-language-hint': await wrapTestComponent('sw-product-document-language-hint', {
                            sync: true,
                        }),
                        'i18n-t': { template: '<span><slot name="language"/></span>' },
                        'mt-floating-ui': useRealFloatingUi
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
        emitEvent = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        getLanguage.mockReset().mockResolvedValue({ name: 'English' });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
        jest.useRealTimers();
        Shopware.Context.api.languageId = originalLanguage;
        Shopware.Store.get('context').api.systemLanguageId = originalSystemLanguage;
    });

    it('requests document upload in the default language without showing the language hint', async () => {
        wrapper = await createWrapper();
        await wrapper.get('button').trigger('click');
        await wrapper.get('button').trigger('mouseenter');
        expect(wrapper.emitted('documents-add')).toEqual([[]]);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(getLanguage).not.toHaveBeenCalled();
    });

    it('disables the Add documents button when adding is not allowed', async () => {
        wrapper = await createWrapper(true);
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
    });

    it('waits for the system language ID before loading its name', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        Shopware.Store.get('context').api.systemLanguageId = null;
        wrapper = await createWrapper();
        await flushPromises();
        expect(getLanguage).not.toHaveBeenCalled();

        Shopware.Store.get('context').api.systemLanguageId = originalSystemLanguage;
        await flushPromises();
        expect(getLanguage).toHaveBeenCalledTimes(1);
        expect(getLanguage).toHaveBeenCalledWith(originalSystemLanguage);
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('focusin');
        expect(wrapper.get('[role="dialog"]').text()).toContain("'English'");
    });

    it('shows the language hint after the language name loads and reuses it on later hovers', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        let resolveLanguage!: (language: { name: string }) => void;
        getLanguage.mockReturnValue(
            new Promise<{ name: string }>((resolve) => {
                resolveLanguage = resolve;
            }),
        );
        wrapper = await createWrapper();
        expect(getLanguage).toHaveBeenCalledTimes(1);
        jest.useFakeTimers();
        const trigger = wrapper.get('.sw-product-document-add-button__trigger');
        await trigger.trigger('mouseenter');
        jest.advanceTimersByTime(100);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        resolveLanguage({ name: 'English' });
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain("'English'");
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('sw-product.documentForm.defaultLanguage');

        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        await trigger.trigger('mouseenter');
        jest.advanceTimersByTime(100);
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain("'English'");
        expect(getLanguage).toHaveBeenCalledTimes(1);
    });

    it('does not open a pending hint after the pointer has left', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        let resolveLanguage!: (language: { name: string }) => void;
        getLanguage.mockReturnValue(
            new Promise<{ name: string }>((resolve) => {
                resolveLanguage = resolve;
            }),
        );
        wrapper = await createWrapper();
        jest.useFakeTimers();
        const trigger = wrapper.get('.sw-product-document-add-button__trigger');
        await trigger.trigger('mouseenter');
        jest.advanceTimersByTime(100);
        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(150);
        await nextTick();

        resolveLanguage({ name: 'English' });
        await flushPromises();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('shows a stable fallback when the language name cannot be loaded', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        getLanguage.mockRejectedValueOnce(new Error('Language unavailable'));
        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain('sw-product.documentForm.defaultLanguage');
        expect(getLanguage).toHaveBeenCalledTimes(1);
        expect(document.activeElement).toBe(wrapper.get('button.mt-link').element);
        await wrapper.get('button.mt-link').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });

    it('shows the hint after the hover delay and ignores brief hovers', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        jest.useFakeTimers();
        const trigger = wrapper.get('.sw-product-document-add-button__trigger');

        await trigger.trigger('mouseenter');
        jest.advanceTimersByTime(50);
        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await trigger.trigger('mouseenter');
        jest.advanceTimersByTime(99);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        jest.advanceTimersByTime(1);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);

        const hint = wrapper.get('[role="dialog"]').element;
        await trigger.trigger('mouseleave');
        await wrapper.get('[role="dialog"]').trigger('mouseenter');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.get('[role="dialog"]').element).toBe(hint);
    });

    it.each([true, false])(
        'explains that inheritance must be removed before adding documents (default language: %s)',
        async (isDefaultLanguage) => {
            if (!isDefaultLanguage) {
                Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
            }
            wrapper = await createWrapper(false, false, true);
            expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
            const trigger = wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger');
            trigger.element.focus();
            await flushPromises();
            const hint = wrapper.get('[role="dialog"]');
            expect(hint.text()).toContain('sw-product.documentForm.unlinkToAdd');
            const descriptionId = trigger.attributes('aria-describedby');
            expect(descriptionId).toBeTruthy();
            expect(document.getElementById(descriptionId ?? '')?.textContent).toContain(
                'sw-product.documentForm.unlinkToAdd',
            );
            expect(trigger.attributes('aria-label')).toContain(wrapper.get('button').text());
            expect(wrapper.find('button.mt-link').exists()).toBe(false);
            expect(emitEvent).not.toHaveBeenCalledWith('on-change-language-clicked', expect.anything());
            expect(wrapper.emitted('documents-add')).toBeUndefined();
            expect(getLanguage).not.toHaveBeenCalled();
        },
    );

    it.each(['Enter', ' '])('opens the inherited explanation with %p and closes it on Escape', async (key) => {
        wrapper = await createWrapper(false, false, true);
        const trigger = wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger');
        const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
        trigger.element.dispatchEvent(event);
        await flushPromises();
        expect(event.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(wrapper.get('[role="dialog"]').element);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(document.activeElement).toBe(trigger.element);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.emitted('documents-add')).toBeUndefined();
        expect(emitEvent).not.toHaveBeenCalledWith('on-change-language-clicked', expect.anything());
    });

    it('offers a language switch on focus instead of uploading in another content language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('focusin');
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain("'English'");
        await wrapper.get('button.mt-link').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
        expect(wrapper.emitted('documents-add')).toBeUndefined();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('keeps the hint open while the pointer moves from the button to its link and closes on leaving', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        jest.useFakeTimers();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('mouseenter');
        jest.advanceTimersByTime(100);
        await nextTick();
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

    it.each(['Enter', ' '])('supports %p activation and returns focus on Escape', async (key) => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
        wrapper.get('.sw-product-document-add-button__trigger').element.dispatchEvent(event);
        await flushPromises();
        expect(event.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(wrapper.get('button.mt-link').element);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(document.activeElement).toBe(wrapper.get('.sw-product-document-add-button__trigger').element);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('includes the visible button label in its accessible name', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        const trigger = wrapper.get('.sw-product-document-add-button__trigger');
        expect(trigger.attributes('aria-label')).toContain(wrapper.get('button').text());
        expect(trigger.attributes('aria-label')).toContain('sw-product.documentForm.switchContentLanguage');
    });

    it('keeps the hint open while its link is focused and closes it after focus leaves', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        jest.useFakeTimers();
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('mouseenter');
        jest.advanceTimersByTime(100);
        await nextTick();
        const link = wrapper.get<HTMLButtonElement>('button.mt-link');
        link.element.focus();
        await wrapper.get('[role="dialog"]').trigger('mouseleave');
        jest.advanceTimersByTime(150);
        await nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        expect(document.activeElement).toBe(link.element);

        const outside = document.createElement('button');
        document.body.appendChild(outside);
        try {
            outside.focus();
            jest.advanceTimersByTime(150);
            await nextTick();
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        } finally {
            outside.remove();
        }
    });

    it('keeps the hint open while its trigger is hovered after focus leaves', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        const trigger = wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger');
        trigger.element.focus();
        await flushPromises();
        // jsdom does not compute pointer hover state.
        const matches = trigger.element.matches.bind(trigger.element);
        const hovered = jest
            .spyOn(trigger.element, 'matches')
            .mockImplementation((selector) => selector === ':hover' || matches(selector));
        const outside = document.createElement('button');
        document.body.appendChild(outside);
        jest.useFakeTimers();
        try {
            outside.focus();
            jest.advanceTimersByTime(150);
            await nextTick();
            expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
            hovered.mockRestore();
            await trigger.trigger('mouseleave');
            jest.advanceTimersByTime(150);
            await nextTick();
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        } finally {
            hovered.mockRestore();
            outside.remove();
        }
    });

    it('focuses the inherited explanation in the real Meteor floating panel', async () => {
        wrapper = await createWrapper(false, true, true);
        await wrapper.get('.sw-product-document-add-button__trigger').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        const hint = document.getElementById(
            wrapper.get('.sw-product-document-add-button__trigger').attributes('aria-describedby') ?? '',
        );
        expect(hint?.textContent).toContain('sw-product.documentForm.unlinkToAdd');
        expect(document.activeElement).toBe(hint);
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
        expect(wrapper.emitted('documents-add')).toBeUndefined();
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
