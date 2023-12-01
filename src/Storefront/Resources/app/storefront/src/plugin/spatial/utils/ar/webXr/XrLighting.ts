import type { Scene, WebGLRenderer, HemisphereLight } from 'three';
import type { XREstimatedLight } from 'three/examples/jsm/webxr/XREstimatedLight';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class XrLighting {

    private defaultLight: HemisphereLight;
    private xrLight: XREstimatedLight;

    private scene: Scene;
    private renderer: WebGLRenderer;

    /**
     * Adds lighting to the scene based on the XR environment
     * @param scene
     * @param renderer
     */
    constructor(scene: Scene, renderer: WebGLRenderer) {
        this.scene = scene;
        this.renderer = renderer;

        // eslint-disable-next-line
        this.defaultLight = new window.threeJs.HemisphereLight(0xffffff, 0xbbbbff, 1);
        this.defaultLight.position.set(0.5, 1, 0.25);
        this.scene.add(this.defaultLight);

        // eslint-disable-next-line
        this.xrLight = new window.threeJsAddons.XREstimatedLight( this.renderer );

        this.initializeEventListeners();
    }

    /**
     * initialize event listeners
     * @private
     */
    private initializeEventListeners(): void {
        this.xrLight.addEventListener('estimationstart', this.onEstimationStart.bind(this));
        this.xrLight.addEventListener('estimationend', this.onEstimationEnd.bind(this));
    }

    /**
     * when the light estimation starts, switch to xr light in the scene
     * @private
     */
    private onEstimationStart(): void {
        this.scene.add(this.xrLight);
        this.scene.remove(this.defaultLight);
        if (this.xrLight.environment) {
            this.scene.environment = this.xrLight.environment;
        }
    }

    /**
     * when the light estimation ends, switch back to the default light
     * @private
     */
    private onEstimationEnd(): void {
        this.scene.add(this.defaultLight);
        this.scene.remove(this.xrLight);
        this.scene.environment = null;
    }

    /**
     * dispose of the lighting
     */
    public dispose(): void {
        this.xrLight.removeEventListener('estimationstart', this.onEstimationStart.bind(this));
        this.xrLight.removeEventListener('estimationend', this.onEstimationEnd.bind(this));
    }
}
