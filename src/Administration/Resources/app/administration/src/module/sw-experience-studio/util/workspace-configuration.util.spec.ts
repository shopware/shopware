import { copyWorkspaceConfiguration, createWorkspaceConfiguration } from './workspace-configuration.util';

describe('workspace configuration serialization', () => {
    it('preserves independent identities and preview states for frames of the same layout', () => {
        const desktop = {
            id: 'desktop-preview',
            layoutId: 'shared-layout',
            x: 0,
            y: 0,
            width: 1480,
            height: 900,
            viewport: 'desktop' as const,
            previewEntityId: 'product-a',
            languageId: 'system-language',
        };
        const mobile = {
            ...desktop,
            id: 'mobile-preview',
            x: 1000,
            width: 375,
            viewport: 'mobile' as const,
            previewEntityId: 'product-b',
            languageId: 'german-language',
        };
        const configuration = { ...createWorkspaceConfiguration(), frames: [desktop, mobile] };

        const snapshot = copyWorkspaceConfiguration(configuration);

        expect(snapshot.frames).toEqual([desktop, mobile]);
        mobile.width = 600;
        expect(snapshot.frames[1].width).toBe(375);
    });

    it('copies geometry and context while excluding layout content and editor state', () => {
        const frame = {
            id: 'preview',
            layoutId: 'layout',
            x: -40,
            y: 10,
            width: 640,
            height: 900,
            viewport: 'custom' as const,
            previewEntityId: 'product',
            layout: [{ component: 'secret' }],
            selectedElementId: 'element',
        };
        const configuration = { ...createWorkspaceConfiguration(), frames: [frame] };
        const snapshot = copyWorkspaceConfiguration(configuration);
        expect(snapshot.frames[0]).toEqual({
            id: 'preview',
            layoutId: 'layout',
            x: -40,
            y: 10,
            width: 640,
            height: 900,
            viewport: 'custom',
            previewEntityId: 'product',
        });
        frame.x = 900;
        expect(snapshot.frames[0].x).toBe(-40);
    });
});
