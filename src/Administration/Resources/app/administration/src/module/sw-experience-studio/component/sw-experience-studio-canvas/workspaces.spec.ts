import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { createWorkspaceConfiguration } from '../../util/workspace-configuration.util';

describe('canvas workspace restoration', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it.each([
        0,
        1,
    ])('shows opened pages by default when entering a workspace with %i frames', async (frameCount) => {
        const configuration = createWorkspaceConfiguration();
        configuration.frames = Array.from({ length: frameCount }, () => ({
            layoutId: 'layout-a',
            x: 0,
            y: 0,
            width: 700,
            height: 900,
            viewport: 'desktop',
            previewEntityId: null,
        }));
        wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-canvas', { sync: true }), {
            slots: {
                layouts: '<div class="opened-pages">Opened pages</div>',
                structure: '<div class="page-tree">Tree</div>',
            },
            props: {
                workspaceConfiguration: configuration,
                frames: configuration.frames.map((frame) => ({
                    id: frame.layoutId,
                    layout: { name: 'Layout' },
                    viewport: frame.viewport,
                    previewEntityId: null,
                    isActive: true,
                })),
            },
        });
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-canvas__sidebar .opened-pages').text()).toBe('Opened pages');
        expect(wrapper.find('.page-tree').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        expect(
            wrapper.find('.sw-experience-studio-canvas__sidebar .sw-experience-studio-canvas__panel-header').exists(),
        ).toBe(false);
    });

    it('shows opened pages by default when creating a standalone layout', async () => {
        wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-canvas', { sync: true }), {
            slots: {
                layouts: '<div class="opened-pages">Opened pages</div>',
                structure: '<div class="page-tree">Tree</div>',
            },
        });
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-canvas__sidebar .opened-pages').text()).toBe('Opened pages');
        expect(wrapper.find('.page-tree').exists()).toBe(false);
    });

    it('keeps pointer resizing while resize handles no longer respond to arrow keys or appear in the tab order', async () => {
        const configuration = createWorkspaceConfiguration();
        configuration.frames = [
            { layoutId: 'layout-a', x: 0, y: 0, width: 700, height: 500, viewport: 'custom', previewEntityId: null },
        ];
        wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-canvas', { sync: true }), {
            props: {
                workspaceConfiguration: configuration,
                frames: [
                    {
                        id: 'layout-a',
                        layout: { name: 'Layout' },
                        viewport: 'custom',
                        previewEntityId: null,
                        isActive: true,
                    },
                ],
            },
        });
        await flushPromises();
        const frame = wrapper.get('[data-frame-id="layout-a"]');
        const handle = frame.get('.sw-experience-studio-canvas__resize-handle.is--se');
        const initialStyle = frame.attributes('style');
        expect(handle.attributes('tabindex')).toBe('-1');

        await handle.trigger('keydown', { key: 'ArrowRight' });
        await handle.trigger('keydown', { key: 'ArrowDown', shiftKey: true });
        expect(frame.attributes('style')).toBe(initialStyle);
        expect(wrapper.emitted('frame-viewport-change')).toBeUndefined();

        await handle.trigger('pointerdown', { button: 0, pointerId: 1, clientX: 100, clientY: 100 });
        const move = new MouseEvent('pointermove', { clientX: 140, clientY: 120 });
        Object.defineProperty(move, 'pointerId', { value: 1 });
        document.dispatchEvent(move);
        await flushPromises();
        document.dispatchEvent(new Event('pointerup'));
        await flushPromises();

        expect(frame.attributes('style')).toContain('width: 740px');
        expect(frame.attributes('style')).toContain('height: 520px');
        expect(wrapper.emitted('frame-viewport-change')).toContainEqual(['layout-a', 'custom']);
    });

    it('restores exact frame geometry and camera without reading legacy positions or autosaving', async () => {
        const configuration = createWorkspaceConfiguration();
        configuration.frames = [
            { layoutId: 'layout-a', x: -120, y: 75, width: 641, height: 815, viewport: 'custom', previewEntityId: null },
        ];
        configuration.canvas = { panX: 45, panY: -90, zoom: 0.75 };
        localStorage.setItem(
            'sw-experience-studio-canvas-position.user.layout-a',
            JSON.stringify({ x: 999, y: 999, isManuallyPlaced: true }),
        );
        wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-canvas', { sync: true }), {
            props: {
                workspaceConfiguration: configuration,
                layoutId: 'layout-a',
                frames: [
                    {
                        id: 'layout-a',
                        layout: { name: 'Layout' },
                        viewport: 'custom',
                        previewEntityId: null,
                        isActive: true,
                    },
                ],
            },
        });
        await flushPromises();
        expect(wrapper.find('[data-frame-id="layout-a"]').attributes('style')).toContain('width: 641px');
        expect(wrapper.find('[data-frame-id="layout-a"]').attributes('style')).toContain('height: 815px');
        expect(wrapper.find('.sw-experience-studio-canvas__world').attributes('style')).toContain(
            'translate3d(45px, -90px, 0) scale(0.75)',
        );
        expect(wrapper.emitted('configuration-change')).toBeUndefined();
    });
});
