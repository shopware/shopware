// @ts-ignore
import Plugin from 'src/plugin-system/plugin.class';
// @ts-ignore
import type NativeEventEmitter from 'src/helper/emitter.helper';
import type { Clock, PerspectiveCamera, Scene, WebGLRenderer } from 'three';
import { loadThreeJs } from './utils/spatial-threejs-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
// @ts-ignore
export default class SpatialBaseViewerPlugin extends Plugin {

    protected rendering : boolean;

    public canvas: HTMLCanvasElement | undefined;
    public camera: PerspectiveCamera | undefined;
    public scene: Scene | undefined;
    public renderer: WebGLRenderer | undefined;

    public clock: Clock | undefined;

    public ready = false;
    $emitter: NativeEventEmitter;

    /**
     * initialize plugin
     */
    public async init() {
        await loadThreeJs();

        this.initViewer(true);
    }

    /**
     * initialize the viewer
     * @param force - Will reinitialize the viewer entirely. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public initViewer(force: boolean) {
        this.setReady(false);
        // @ts-ignore
        this.canvas = this.el as HTMLCanvasElement;
        if (this.camera == undefined || force) {
            // eslint-disable-next-line
            this.camera = new window.threeJs.PerspectiveCamera( 70, this.canvas.clientWidth / this.canvas.clientHeight, 0.01, 10 );
        }
        if (this.scene == undefined || force) {
            // eslint-disable-next-line
            this.scene = new window.threeJs.Scene();
        }

        this.rendering = false;

        // eslint-disable-next-line
        this.clock = new window.threeJs.Clock();

        // eslint-disable-next-line
        this.renderer = new window.threeJs.WebGLRenderer({
            canvas: this.canvas,
            antialias: true,
        });
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
        requestAnimationFrame(this.render.bind(this));

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

    /**
     * Render loop
     * @private
     */
    private render() {
        if (!this.rendering) {
            return;
        }
        requestAnimationFrame(this.render.bind(this));
        if (!this.clock) {
            return;
        }
        const delta = this.clock.getDelta();

        this.preRender(delta);
        if (this.camera != undefined && this.scene != undefined && this.renderer != undefined) {
            this.renderer.render(this.scene, this.camera);
        }

        this.postRender(delta);
    }

    // eslint-disable-next-line @typescript-eslint/no-empty-function, @typescript-eslint/no-unused-vars
    protected preRender(delta: number) {}

    // eslint-disable-next-line @typescript-eslint/no-empty-function, @typescript-eslint/no-unused-vars
    protected postRender(delta: number) {}

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
