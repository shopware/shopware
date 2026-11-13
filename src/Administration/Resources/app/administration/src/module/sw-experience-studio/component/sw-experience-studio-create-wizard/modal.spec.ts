import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';

async function createWrapper(props = {}) {
    return shallowMount((await wrapTestComponent('sw-experience-studio-create-wizard', { sync: true })) as Component, {
        props: {
            name: '  My layout  ',
            selectedType: 'category',
            typeOptions: [{ value: 'category', label: 'Category' }],
            ...props,
        },
        global: {
            stubs: {
                'sw-modal': {
                    props: ['title'],
                    template: `<div class="test-modal"><header><h2>{{ title }}</h2><button class="test-close" @click="$emit('modal-close')">Close</button></header><main><slot /></main><footer><slot name="modal-footer" /></footer></div>`,
                },
            },
        },
    });
}

describe('create layout modal', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('renders the form directly in the modal body with one title and actions in its footer', async () => {
        wrapper = await createWrapper();

        expect(wrapper.findAll('.test-modal')).toHaveLength(1);
        expect(wrapper.findAll('h2')).toHaveLength(1);
        expect(wrapper.get('header h2').text()).toBe('sw-experience-studio.createWizard.title');
        expect(wrapper.find('.sw-experience-studio-create-wizard__content').exists()).toBe(false);
        expect(wrapper.get('main').find('.sw-experience-studio-create-wizard__name').exists()).toBe(true);
        expect(wrapper.get('main').find('.sw-experience-studio-create-wizard__type-options').exists()).toBe(true);
        expect(wrapper.get('footer').findAll('button')).toHaveLength(2);
        expect(wrapper.get('main').findAll('button.mt-button')).toHaveLength(0);
    });

    it('creates the layout from the modal footer using the existing trimmed-name payload', async () => {
        wrapper = await createWrapper();
        await wrapper.get('footer').findAll('button')[1].trigger('click');

        expect(wrapper.emitted('complete')).toEqual([[{ name: 'My layout', type: 'category' }]]);
    });

    it('keeps creation disabled until the name and type are supplied', async () => {
        wrapper = await createWrapper({ name: '', selectedType: null });
        expect(wrapper.get('footer').findAll('button')[1].attributes('disabled')).toBeDefined();
        await wrapper.setProps({ name: 'My layout', selectedType: 'category' });
        expect(wrapper.get('footer').findAll('button')[1].attributes('disabled')).toBeUndefined();
    });

    it.each(['footer button', '.test-close'])('cancels from %s', async (selector) => {
        wrapper = await createWrapper();
        await wrapper.get(selector).trigger('click');

        expect(wrapper.emitted('cancel')).toEqual([[]]);
    });
});
