import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';

const frames = [
    {
        id: 'english-preview',
        layout: { id: 'layout', name: 'Product page' },
        languageLabel: 'English',
        viewport: 'desktop',
        previewEntityId: null,
        isActive: true,
    },
    {
        id: 'german-preview',
        layout: { id: 'layout', name: 'Product page' },
        languageLabel: 'German',
        viewport: 'mobile',
        previewEntityId: null,
        isActive: false,
    },
];

async function createWrapper() {
    const wrapper = shallowMount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        props: { frames },
        slots: {
            'frame-controls': '<button class="test-history">Undo</button>',
        },
        global: {
            stubs: {
                'sw-experience-studio-frame-menu': await wrapTestComponent('sw-experience-studio-frame-menu', {
                    sync: true,
                }),
            },
        },
    });
    await flushPromises();
    return wrapper;
}

describe('Experience Studio floating frame toolbar', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('keeps the layout name and language above every preview and only shows controls for the active frame', async () => {
        wrapper = await createWrapper();
        const inactiveFrame = wrapper.get('[data-frame-id="german-preview"]');
        const heading = inactiveFrame.get('.sw-experience-studio-canvas__frame-header');

        expect(heading.get('.sw-experience-studio-canvas__frame-name').text()).toBe('Product page');
        expect(heading.get('.sw-experience-studio-canvas__frame-language').text()).toBe('German');
        expect(heading.find('.sw-experience-studio-canvas__close-frame').exists()).toBe(true);
        expect(inactiveFrame.find('.sw-experience-studio-canvas__frame-toolbar').exists()).toBe(false);
        expect(wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
        const toolbar = wrapper.get('[data-frame-id="english-preview"] .sw-experience-studio-canvas__frame-toolbar');
        expect(toolbar.find('.sw-experience-studio-canvas__frame-name').exists()).toBe(false);
        expect(toolbar.find('.sw-experience-studio-canvas__close-frame').exists()).toBe(false);
        expect(toolbar.get('.test-history').element.compareDocumentPosition(toolbar.get('[role="group"]').element)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );
    });

    it('moves the toolbar with the active selection and removes it when all frames are inactive', async () => {
        wrapper = await createWrapper();
        await wrapper.get('[data-frame-id="german-preview"] .sw-experience-studio-canvas__move-handle').trigger('click');

        expect(wrapper.emitted('frame-activate')).toContainEqual(['german-preview']);
        await wrapper.setProps({ frames: frames.map((frame) => ({ ...frame, isActive: frame.id === 'german-preview' })) });

        expect(wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
        const germanFrame = wrapper.get('[data-frame-id="german-preview"]');
        expect(germanFrame.find('.sw-experience-studio-canvas__frame-toolbar').exists()).toBe(true);
        expect(wrapper.find('[data-frame-id="english-preview"] .sw-experience-studio-canvas__frame-toolbar').exists()).toBe(
            false,
        );

        await wrapper.setProps({ frames: frames.map((frame) => ({ ...frame, isActive: false })) });

        expect(wrapper.find('.sw-experience-studio-canvas__frame-toolbar').exists()).toBe(false);
        expect(wrapper.findAll('.sw-experience-studio-canvas__frame-header')).toHaveLength(2);
    });

    it('routes viewport and frame actions to the active preview while keeping close on each frame header', async () => {
        wrapper = await createWrapper();
        await wrapper
            .get('[data-frame-id="english-preview"] button[aria-label="sw-experience-studio.detail.toolbar.viewportMobile"]')
            .trigger('click');
        expect(wrapper.emitted('frame-viewport-change')).toEqual([['english-preview', 'mobile']]);

        await wrapper.get('.sw-experience-studio-frame-menu__translate').trigger('click');
        await wrapper.get('.sw-experience-studio-frame-menu__open-another').trigger('click');
        expect(wrapper.emitted('frame-translate')).toEqual([['english-preview']]);
        expect(wrapper.emitted('frame-open-another')).toEqual([['english-preview']]);

        await wrapper.get('[data-frame-id="german-preview"] .sw-experience-studio-canvas__close-frame').trigger('click');
        expect(wrapper.emitted('frame-close')).toEqual([['german-preview']]);
    });
    it.each([
        ['panning the canvas', '.sw-experience-studio-canvas__background'],
        ['moving a frame', '.sw-experience-studio-canvas__move-handle'],
    ])('keeps the active toolbar available when %s starts', async (_action, selector) => {
        wrapper = await createWrapper();

        await wrapper.get(selector).trigger('pointerdown', {
            button: 0,
            pointerId: 1,
            clientX: 400,
            clientY: 300,
        });

        expect(wrapper.get('.sw-experience-studio-canvas').classes()).toContain('is--gesturing');
        expect(wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
    });

    it('keeps the active toolbar available when scrolling to zoom the canvas', async () => {
        wrapper = await createWrapper();
        const world = wrapper.get('.sw-experience-studio-canvas__world');
        const previousTransform = world.attributes('style');

        wrapper.get('.sw-experience-studio-canvas__background').element.dispatchEvent(
            new WheelEvent('wheel', {
                bubbles: true,
                cancelable: true,
                deltaY: -120,
                clientX: 400,
                clientY: 300,
            }),
        );
        await flushPromises();

        expect(world.attributes('style')).not.toBe(previousTransform);
        expect(wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
    });
});
