// @ts-ignore
import Plugin from 'src/plugin-system/plugin.class';
// @ts-ignore
import type NativeEventEmitter from 'src/helper/emitter.helper';
import type { Clock, PerspectiveCamera, Scene, WebGLRenderer } from 'three';
import { loadDIVE } from './utils/spatial-dive-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
// @ts-ignore
export default class SpatialBaseViewerPlugin extends Plugin {

    protected rendering = false;

    public canvas: HTMLCanvasElement | undefined;
    public camera: PerspectiveCamera | undefined;
    public scene: Scene | undefined;
    public renderer: WebGLRenderer | undefined;

    public clock: Clock | undefined;

    public ready = false;
    $emitter: NativeEventEmitter;

    // eslint-disable-next-line @typescript-eslint/consistent-type-imports
    protected _dive: import('@shopware-ag/dive').DIVE | undefined;

    /**
     * initialize plugin
     */
    public async init() {
        await loadDIVE();

        await this.initViewer();
    }

    /**
     * initialize the viewer
     * @param force - Will reinitialize the viewer entirely. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public async initViewer() {
        this.setReady(false);
        // @ts-ignore
        this.canvas = this.el as HTMLCanvasElement;
        this.canvas.tabIndex = 0;

        if (this._dive == undefined) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
            this._dive = await window.DIVEClass.QuickView(this.options.modelUrl, { autoStart: false, canvas: this.el as HTMLCanvasElement });
        }

        // @ts-ignore
        this.$emitter.publish('Viewer/initViewer');
    }

    /**
     * Start rendering loop
     */
    public startRendering() {
        // Prevent multiple render loops
        if (this.rendering) {
            return;
        }

        // start render loop
        this.rendering = true;
        this._dive?.engine.start();

        // Add classes to canvas parent
        this.canvas?.parentElement?.classList.add('spatial-canvas-rendering');

        if (this.ready) {
            this.canvas?.parentElement?.classList.add('spatial-canvas-display');
        }

        // Publish events
        // @ts-ignore
        this.$emitter.publish('Viewer/startRendering');
    }

    /**
     * Stop rendering loop
     */
    public stopRendering() {
        // stop render loop
        this.rendering = false;

        // Remove classes from canvas parent
        this.canvas?.parentElement?.classList.remove('spatial-canvas-rendering');

        // Publish events
        // @ts-ignore
        this.$emitter.publish('Viewer/stopRendering');
    }

    public setReady(ready: boolean) {
        if (this.ready === ready) {
            return;
        }
        this.ready = ready;
        this.onReady(ready);
    }

    protected onReady(state: boolean) {
        if (this.canvas == undefined) {
            return;
        }
        if (state) {
            // @ts-ignore
            this.$emitter.publish('Viewer/ready');
            this.canvas.parentElement?.classList.add('spatial-canvas-ready');
            if (this.rendering) {
                this.canvas.parentElement?.classList.add('spatial-canvas-display');
            }
        } else {
            // @ts-ignore
            this.$emitter.publish('Viewer/notReady');
            this.canvas.parentElement?.classList.remove('spatial-canvas-ready');
            this.canvas.parentElement?.classList.remove('spatial-canvas-display');
        }
    }
}
