import type SpatialBaseViewerPlugin from '../spatial-base-viewer.plugin';
// @ts-ignore
import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class SpatialMovementNoteUtil {

    private plugin: SpatialBaseViewerPlugin;
    private note: HTMLElement | undefined;

    static options = {
        noteSelector: '[data-spatial-movement-note]',
        hiddenClass: 'spatial-canvas-note--hidden',
        touchTextDataAttribute: 'data-spatial-movement-note-touch-text',
    };

    constructor(plugin: SpatialBaseViewerPlugin) {
        this.plugin = plugin;

        if (!this.plugin.canvas) {
            return;
        }

        this.note = this.plugin.canvas?.parentElement?.querySelector(SpatialMovementNoteUtil.options.noteSelector) ?? undefined;

        // check if the device is a touch device
        // @ts-ignore
        if (DeviceDetection.isTouchDevice()) {
            const touchText = this.note?.getAttribute(SpatialMovementNoteUtil.options.touchTextDataAttribute);
            if (touchText && this.note) {
                this.note.innerText = touchText;
            }
        }

        this.plugin.canvas.addEventListener('pointerup', this.onMove.bind(this));
    }

    private onMove() {
        this.note?.classList.add(SpatialMovementNoteUtil.options.hiddenClass);
        if (this.plugin.canvas) {
            this.plugin.canvas.removeEventListener('pointerup', this.onMove.bind(this));
        }
    }
}
