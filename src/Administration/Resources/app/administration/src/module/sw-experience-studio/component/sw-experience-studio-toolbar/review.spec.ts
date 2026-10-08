import { DOMWrapper, mount } from '@vue/test-utils';
import type { Component } from 'vue';

async function createWrapper(props = {}) {
    return mount((await wrapTestComponent('sw-experience-studio-toolbar', { sync: true })) as Component, {
        attachTo: document.body,
        props: {
            allowSave: true,
            changedLayouts: [
                { id: 'about', name: 'About us' },
                { id: 'faq', name: 'FAQ' },
            ],
            ...props,
        },
        global: { stubs: { 'sw-entity-single-select': true } },
    });
}

function getPanel() {
    const element = document.body.querySelector<HTMLElement>('.sw-experience-studio-toolbar__review-panel');
    if (!element) {
        throw new Error('Review panel is not open');
    }
    return new DOMWrapper(element);
}

describe('toolbar change review', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    async function openReview() {
        await wrapper.get('.sw-experience-studio-toolbar__review-trigger').trigger('click');
        await flushPromises();
    }

    it('shows a changed-layout counter and lists the changed layouts without saving on opening', async () => {
        wrapper = await createWrapper();
        expect(wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('2');
        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').classes()).toContain('mt-button--secondary');

        await openReview();

        expect(
            getPanel()
                .findAll('.sw-experience-studio-toolbar__changed-layout-name')
                .map((name) => name.text()),
        ).toEqual([
            'About us',
            'FAQ',
        ]);
        expect(wrapper.emitted('save')).toBeUndefined();
        expect(getPanel().attributes('role')).toBe('dialog');
        expect(document.activeElement).toBe(getPanel().get('button').element);
    });

    it('discards only the selected layout and updates the remaining list and count', async () => {
        wrapper = await createWrapper();
        await openReview();
        await getPanel().findAll('li button')[0].trigger('click');

        expect(wrapper.emitted('discard-layout')).toEqual([['about']]);
        expect(wrapper.emitted('save')).toBeUndefined();
        await wrapper.setProps({ changedLayouts: [{ id: 'faq', name: 'FAQ' }] });

        expect(wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('1');
        expect(getPanel().findAll('li')).toHaveLength(1);
        expect(getPanel().get('.sw-experience-studio-toolbar__changed-layout-name').text()).toBe('FAQ');
    });

    it('saves from the footer and closes once all changed layouts are saved', async () => {
        wrapper = await createWrapper();
        await openReview();
        const saveButton = getPanel().get('.sw-experience-studio-toolbar__review-footer button');
        expect(saveButton.text()).toBe('sw-experience-studio.workspace.saveAll');
        await saveButton.trigger('click');
        expect(wrapper.emitted('save')).toEqual([[]]);

        await wrapper.setProps({ changedLayouts: [] });
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-experience-studio-toolbar__change-count').exists()).toBe(false);
    });

    it('disables review when no layouts have changed', async () => {
        wrapper = await createWrapper({ changedLayouts: [] });
        await openReview();

        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('disabled')).toBeDefined();
        expect(document.body.querySelector('.sw-experience-studio-toolbar__review-panel')).toBeNull();
    });

    it.each([{ allowSave: false }, { isLoading: true }, { isSavingAll: true }])(
        'blocks save and discard when actions are disabled: %j',
        async (props) => {
            wrapper = await createWrapper();
            await openReview();
            await wrapper.setProps(props);

            const buttons = getPanel().findAll('button');
            expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
            await buttons[0].trigger('click');
            await buttons[buttons.length - 1].trigger('click');
            expect(wrapper.emitted('discard-layout')).toBeUndefined();
            expect(wrapper.emitted('save')).toBeUndefined();
        },
    );

    it('closes on Escape and restores focus to the trigger', async () => {
        wrapper = await createWrapper();
        await openReview();
        await getPanel().get('button').trigger('keydown', { key: 'Escape' });

        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(wrapper.get('.sw-experience-studio-toolbar__review-trigger').element);
    });

    it('closes when clicking outside the review', async () => {
        wrapper = await createWrapper();
        await openReview();
        document.body.click();
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('aria-expanded')).toBe('false');
    });
});
