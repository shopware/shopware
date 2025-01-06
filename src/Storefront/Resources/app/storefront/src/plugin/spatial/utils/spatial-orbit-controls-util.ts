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

    private unbindEventListeners: () => void;

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

        this.unbindEventListeners = this.bindEventListeners(canvas);
        /* eslint-enable */
    }

    private bindEventListeners(canvas: HTMLCanvasElement): () => void {
        canvas.addEventListener('keydown', this.onKeyDown.bind(this));

        return () => {
            canvas.removeEventListener('keydown', this.onKeyDown.bind(this));
        };
    }

    private onKeyDown(event: KeyboardEvent): void {
        if (event.key !== 'ArrowRight'
            && event.key !== 'ArrowLeft'
            && event.key !== 'ArrowUp'
            && event.key !== 'ArrowDown'
            && event.key !== '+'
            && event.key !== '-'
        ) {
            return;
        }

        event.stopPropagation();
        event.preventDefault();

        const rotationSpeed = Math.PI/16;
        const zoomSpeed = Math.PI/16;

        if (event.key === 'ArrowRight') this.move(-rotationSpeed, 0);
        if (event.key === 'ArrowLeft') this.move(rotationSpeed, 0);
        if (event.key === 'ArrowUp') this.move(0, -rotationSpeed);
        if (event.key === 'ArrowDown') this.move(0, rotationSpeed);
        if (event.key === '+') this.zoom(zoomSpeed);
        if (event.key === '-') this.zoom(-zoomSpeed);
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
        this.unbindEventListeners();
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

    public move(x: number, y: number): void {
        const { minAzimuthAngle, maxAzimuthAngle, minPolarAngle, maxPolarAngle } = this.controls;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.controls.minAzimuthAngle = this.controls.maxAzimuthAngle = window.threeJsAddons?.MathUtils.clamp(
            this.controls.getAzimuthalAngle() + x,
            minAzimuthAngle + x,
            maxAzimuthAngle - x,
        );
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.controls.minPolarAngle = this.controls.maxPolarAngle = window.threeJsAddons?.MathUtils.clamp(
            this.controls.getPolarAngle() + y,
            minPolarAngle + y,
            maxPolarAngle - y,
        );
        this.controls.update();
        this.controls.minAzimuthAngle = minAzimuthAngle;
        this.controls.maxAzimuthAngle = maxAzimuthAngle;
        this.controls.minPolarAngle = minPolarAngle;
        this.controls.maxPolarAngle = maxPolarAngle;
    }

    public zoom(by: number): void {
        const { minDistance, maxDistance } = this.controls;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.controls.minDistance = this.controls.maxDistance = window.threeJsAddons?.MathUtils.clamp(
            this.controls.getDistance() - by,
            minDistance + by,
            maxDistance - by,
        );
        this.controls.update();
        this.controls.minDistance = minDistance;
        this.controls.maxDistance = maxDistance;
    }
}
