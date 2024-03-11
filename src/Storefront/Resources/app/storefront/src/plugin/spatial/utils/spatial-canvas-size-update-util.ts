import type SpatialBaseViewerPlugin from '../spatial-base-viewer.plugin';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 *
 * The canvas size update util.
 * This util is responsible for updating the canvas size if the parent element size has changed.
 */
export default class SpatialCanvasSizeUpdateUtil {

    private plugin: SpatialBaseViewerPlugin;

    private lastWidth = 0;
    private lastHeight = 0;

    /**
     * Creates a new instance of the canvas size update component.
     * @param plugin
     */
    constructor(plugin: SpatialBaseViewerPlugin) {
        this.plugin = plugin;
        this.init();
    }

    /**
     * Initializes the component.
     */
    public init() {
        // Set initial last size
        if (this.plugin.canvas) {
            this.lastHeight = this.plugin.canvas.clientHeight;
            this.lastWidth = this.plugin.canvas.clientWidth;
        }
    }

    /**
     * Updates the canvas size if the parent element size has changed.
     */
    public update() {
        if (!this.plugin.canvas || !this.plugin.camera) {
            return;
        }
        // Calculate new canvas size
        const newHeight = this.plugin.canvas.parentElement?.clientHeight ?? 0;
        const newWidth = this.plugin.canvas.parentElement?.clientWidth ?? 0;

        if (newHeight === this.lastHeight && newWidth === this.lastWidth) {
            return;
        }

        // Update canvas size if it has changed
        this.plugin.canvas.height = newHeight;
        this.plugin.canvas.width = newWidth;
        this.plugin.camera.aspect = newWidth / newHeight;
        this.plugin.camera.updateProjectionMatrix();
        this.plugin.renderer?.setSize(newWidth, newHeight);

        // emit event
        // @ts-ignore
        this.plugin.$emitter.publish('CanvasSizeUpdateUtil/sizeUpdate', {
            width: newWidth,
            height: newHeight,
        });

        // Update last size
        this.lastHeight = newHeight;
        this.lastWidth = newWidth;
    }
}
