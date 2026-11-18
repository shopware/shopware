import type { WorkspaceConfiguration, WorkspaceFrame } from '../types/workspace.types';

type StoredWorkspaceConfiguration = Omit<WorkspaceConfiguration, 'frames'> & {
    frames: Array<Omit<WorkspaceFrame, 'id'> & { id?: string }>;
};

/**
 * @private
 * @sw-package discovery
 */
export function createWorkspaceConfiguration(): WorkspaceConfiguration {
    return { frames: [], previewSalesChannelId: null, canvas: { panX: 0, panY: 0, zoom: 1 } };
}

/**
 * Copy only the persisted setup fields. Editor models and layout content never cross this boundary.
 * @private
 * @sw-package discovery
 */
export function copyWorkspaceConfiguration(configuration: StoredWorkspaceConfiguration): WorkspaceConfiguration {
    return {
        frames: configuration.frames.map((frame) => ({
            id: frame.id ?? frame.layoutId,
            layoutId: frame.layoutId,
            x: frame.x,
            y: frame.y,
            width: frame.width,
            height: frame.height,
            viewport: frame.viewport,
            previewEntityId: frame.previewEntityId,
            ...(frame.languageId ? { languageId: frame.languageId } : {}),
        })),
        previewSalesChannelId: configuration.previewSalesChannelId,
        canvas: { panX: configuration.canvas.panX, panY: configuration.canvas.panY, zoom: configuration.canvas.zoom },
    };
}
