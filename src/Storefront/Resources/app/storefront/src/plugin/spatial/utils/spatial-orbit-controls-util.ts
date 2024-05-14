import type { OrbitControls } from 'three/examples/jsm/controls/OrbitControls';
import type { PerspectiveCamera } from 'three';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 *
 * Orbit controls
 */
export default class SpatialOrbitControlsUtil {
    private readonly controls: OrbitControls;

    /**
     * Constructor
     * @param camera
     * @param canvas
     */
    constructor(camera: PerspectiveCamera, canvas: HTMLCanvasElement) {
        /* eslint-disable */
        this.controls = new window.threeJsAddons.OrbitControls(camera, canvas);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.25;
        this.controls.enableZoom = true;
        this.controls.enablePan = false;
        /* eslint-enable */
    }

    /**
     * Update the controls
     */
    public update(): void {
        this.controls.update();
    }

    /**
     * Enable the controls
     */
    public enable(): void {
        this.controls.enabled = true;
    }

    /**
     * Disable the controls
     */
    public disable(): void {
        this.controls.enabled = false;
    }

    /**
     * Dispose the controls
     */
    public dispose(): void {
        this.controls.dispose();
    }

    /**
     * Reset the controls
     */
    public reset(): void {
        this.controls.target.set(0, 0, 0);
    }

    /**
     * Enable the zoom
     */
    public enableZoom(): void {
        this.controls.enableZoom = true;
    }

    /**
     * Disable the zoom
     */
    public disableZoom(): void {
        this.controls.enableZoom = false;
    }
}
