/**
 * @sw-package inventory
 */
import '@testing-library/jest-dom';
import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-add-button', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;
    const getLanguage = jest.fn();

    async function createWrapper(props = {}) {
        return mount(await wrapTestComponent('sw-product-document-add-button', { sync: true }), {
            attachTo: document.body,
            props,
            global: {
                provide: { repositoryFactory: { create: () => ({ get: getLanguage }) } },
                mocks: {
                    $t: (key: string, params?: { language?: string }) =>
                        params?.language ? `${key} ${params.language}` : key,
                },
            },
        });
    }

    function tooltip() {
        const descriptionId = wrapper.get('.sw-product-document-add-button__trigger').attributes('aria-describedby');
        return document.getElementById(descriptionId ?? '');
    }

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        getLanguage.mockReset().mockResolvedValue({ name: 'English' });
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
        jest.restoreAllMocks();
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('requests document upload in the default language', async () => {
        wrapper = await createWrapper();
        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('documents-add')).toEqual([[]]);
        expect(wrapper.find('.sw-product-document-add-button__trigger').exists()).toBe(false);
        expect(getLanguage).not.toHaveBeenCalled();
    });

    it('prevents upload when adding documents is not allowed', async () => {
        wrapper = await createWrapper({ disabled: true });
        await wrapper.get('button').trigger('click');

        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
        expect(wrapper.emitted('documents-add')).toBeUndefined();
    });

    it.each([true, false])(
        'explains inheritance on keyboard focus (default language: %s)',
        async (isSystemDefaultLanguage) => {
            if (!isSystemDefaultLanguage) {
                Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
            }
            wrapper = await createWrapper({ isInherited: true });
            const trigger = wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger');
            trigger.element.focus();
            await nextTick();

            expect(tooltip()).toBeVisible();
            expect(tooltip()?.textContent).toContain('sw-product.documentForm.unlinkToAdd');
            expect(trigger.attributes('aria-disabled')).toBe('true');
            expect(wrapper.get('button').attributes('aria-disabled')).toBe('true');
            expect(wrapper.emitted('documents-add')).toBeUndefined();
        },
    );

    it('keeps the language link open while moving from the button to the tooltip', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();
        const trigger = wrapper.get('.sw-product-document-add-button__trigger');
        await trigger.trigger('mouseover');
        jest.advanceTimersByTime(300);
        await nextTick();

        expect(tooltip()).toBeVisible();
        expect(tooltip()?.textContent).toContain('sw-product.documentForm.emptyDescriptionOtherLanguage');
        expect(tooltip()?.querySelector('a')?.textContent).toBe("'English'");
        expect(document.querySelector('[role="dialog"]')).toBeNull();
        await trigger.trigger('click');
        expect(wrapper.emitted('documents-add')).toBeUndefined();

        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(400);
        await nextTick();
        expect(tooltip()).toBeVisible();

        tooltip()?.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        jest.advanceTimersByTime(1000);
        await nextTick();
        expect(tooltip()).toBeVisible();

        tooltip()?.dispatchEvent(new MouseEvent('mouseleave'));
        jest.advanceTimersByTime(600);
        await nextTick();
        expect(tooltip()).not.toBeVisible();
    });

    it.each([true, false])(
        'switches to the default language through the tooltip link (language loaded: %s)',
        async (loadsLanguage) => {
            Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
            const emitEvent = jest.spyOn(Shopware.Utils.EventBus, 'emit');
            if (!loadsLanguage) {
                getLanguage.mockRejectedValueOnce(new Error('Language unavailable'));
            }
            wrapper = await createWrapper();
            await flushPromises();
            wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger').element.focus();
            await nextTick();

            const link = tooltip()?.querySelector('a');
            expect(link?.textContent).toContain(loadsLanguage ? 'English' : 'sw-product.documentForm.defaultLanguage');
            const click = new MouseEvent('click', { bubbles: true, cancelable: true });
            link?.dispatchEvent(click);
            await nextTick();

            expect(click.defaultPrevented).toBe(true);
            expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
            expect(wrapper.emitted('documents-add')).toBeUndefined();
        },
    );

    it('renders language names as text instead of tooltip markup', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        getLanguage.mockResolvedValueOnce({ name: '<strong>English</strong>' });
        wrapper = await createWrapper();
        await flushPromises();

        expect(tooltip()?.querySelector('a')?.textContent).toBe("'<strong>English</strong>'");
        expect(tooltip()?.querySelector('strong')).toBeNull();
    });

    it('dismisses the explanation with Escape while keeping focus on its trigger', async () => {
        wrapper = await createWrapper({ isInherited: true });
        const trigger = wrapper.get<HTMLElement>('.sw-product-document-add-button__trigger');
        trigger.element.focus();
        await nextTick();
        expect(tooltip()).toBeVisible();

        await trigger.trigger('keydown', { key: 'Escape' });

        expect(tooltip()).not.toBeVisible();
        expect(document.activeElement).toBe(trigger.element);
    });

    it('allows upload after returning to the default content language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        expect(wrapper.get('button').attributes('aria-disabled')).toBe('true');

        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        await nextTick();
        await wrapper.get('button').trigger('click');

        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(false);
        expect(wrapper.emitted('documents-add')).toEqual([[]]);
    });
});
