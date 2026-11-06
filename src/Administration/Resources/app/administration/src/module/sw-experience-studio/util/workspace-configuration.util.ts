import type { WorkspaceConfiguration } from '../types/workspace.types';

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
export function copyWorkspaceConfiguration(configuration: WorkspaceConfiguration): WorkspaceConfiguration {
    return {
        frames: configuration.frames.map((frame) => ({
            layoutId: frame.layoutId,
            x: frame.x,
            y: frame.y,
            width: frame.width,
            height: frame.height,
            viewport: frame.viewport,
            previewEntityId: frame.previewEntityId,
        })),
        previewSalesChannelId: configuration.previewSalesChannelId,
        canvas: { panX: configuration.canvas.panX, panY: configuration.canvas.panY, zoom: configuration.canvas.zoom },
    };
}
