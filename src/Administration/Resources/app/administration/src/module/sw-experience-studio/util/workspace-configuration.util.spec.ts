import { copyWorkspaceConfiguration, createWorkspaceConfiguration } from './workspace-configuration.util';

describe('workspace configuration serialization', () => {
    it('copies geometry and context while excluding layout content and editor state', () => {
        const frame = {
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
