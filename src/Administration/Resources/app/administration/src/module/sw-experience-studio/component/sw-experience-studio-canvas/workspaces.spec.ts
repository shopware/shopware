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
    ])('starts with every panel closed when entering a workspace with %i frames', async (frameCount) => {
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

        expect(wrapper.findAll('.sw-experience-studio-canvas__panel')).toHaveLength(0);
        expect(wrapper.findAll('.sw-experience-studio-canvas__tool').map((tool) => tool.attributes('aria-pressed'))).toEqual(
            [
                'false',
                'false',
                'false',
            ],
        );

        await wrapper
            .get('.sw-experience-studio-canvas__tool[aria-label="sw-experience-studio.detail.canvas.structure"]')
            .trigger('click');
        await wrapper
            .get('.sw-experience-studio-canvas__tool[aria-label="sw-experience-studio.detail.canvas.settings"]')
            .trigger('click');

        expect(wrapper.findAll('.sw-experience-studio-canvas__panel')).toHaveLength(2);
    });

    it('keeps the structure panel available by default when creating a standalone layout', async () => {
        wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-canvas', { sync: true }));
        await flushPromises();

        expect(wrapper.find('.sw-experience-studio-canvas__panel--structure').exists()).toBe(true);
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
