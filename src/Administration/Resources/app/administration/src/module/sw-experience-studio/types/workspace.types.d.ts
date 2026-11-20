/**
 * @private
 * @sw-package discovery
 */
export type WorkspaceViewport = 'mobile' | 'tablet-landscape' | 'desktop' | 'custom';

/** @private @sw-package discovery */
export type WorkspaceFrame = {
    layoutId: string;
    x: number;
    y: number;
    width: number;
    height: number;
    viewport: WorkspaceViewport;
    previewEntityId: string | null;
};

/** @private @sw-package discovery */
export type WorkspaceConfiguration = {
    frames: WorkspaceFrame[];
    previewSalesChannelId: string | null;
    canvas: { panX: number; panY: number; zoom: number };
};

/** @private @sw-package discovery */
export type ContentWorkspace = Omit<Entity<'content_workspace'>, 'configuration'> & {
    configuration: WorkspaceConfiguration;
};
