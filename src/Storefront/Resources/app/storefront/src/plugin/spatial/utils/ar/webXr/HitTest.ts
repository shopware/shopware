import type { Scene, WebGLRenderer } from 'three';
import type { Matrix4, Mesh } from 'three';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class HitTest {

    private renderer: WebGLRenderer;
    private scene: Scene;

    private marker: Mesh;
    private markerVisible: boolean;

    private hitTestSourceRequested: boolean;
    private hitTestSource: XRHitTestSource | null;
    private lastHitPose: Float32Array;

    constructor(renderer: WebGLRenderer, scene: Scene) {
        // define renderer and scene
        this.renderer = renderer;
        this.scene = scene;

        // define marker
        // eslint-disable-next-line
        const ring = new window.threeJs.RingGeometry(0.18, 0.2, 32).rotateX(-Math.PI / 2);
        // eslint-disable-next-line
        const material = new window.threeJs.MeshBasicMaterial();
        // eslint-disable-next-line
        this.marker = new window.threeJs.Mesh(ring, material);
        this.marker.matrixAutoUpdate = false;
        this.marker.visible = false;
        this.scene.add(this.marker);

        // hit test source
        this.lastHitPose = null;
        this.hitTestSource = null;
        this.hitTestSourceRequested = false;
    }

    /**
     * returns a boolean if it hits something
     * @param frame
     */
    public update(frame): boolean {
        if (!(frame instanceof XRFrame)) {
            return false;
        }

        this.updateHits(frame);
        this.updateMarker();
        if (!this.hitTestSourceRequested) {
            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.requestHitTestSource().then();
        }
        return !!this.lastHitPose;
    }

    private updateHits(frame: XRFrame): void {
        if (!this.hitTestSource) {
            return;
        }
        const hitTestResults = frame.getHitTestResults(this.hitTestSource);

        if (hitTestResults.length) {
            const hit = hitTestResults[0];
            const pose = hit.getPose(this.renderer.xr.getReferenceSpace());
            this.lastHitPose = pose.transform.matrix;
        } else {
            this.lastHitPose = null;
        }
    }

    private updateMarker(): void {
        if (this.lastHitPose !== null) {
            this.marker.visible = this.markerVisible;
            this.marker.matrix.fromArray(this.lastHitPose);
        } else {
            this.marker.visible = false;
        }
    }

    public getHitPose(): Matrix4 | null {
        // eslint-disable-next-line
        return new window.threeJs.Matrix4().fromArray(this.lastHitPose);
    }

    public hideMarker(): void {
        this.markerVisible = false;
    }

    public showMarker(): void {
        this.markerVisible = true;
    }

    public dispose() {
        this.hitTestSourceRequested = false;
        this.hitTestSource = null;
        this.marker.visible = false;
        this.scene.remove(this.marker);
    }

    private async requestHitTestSource(): Promise<void> {
        const session = this.renderer.xr.getSession();
        const referenceSpace = await session.requestReferenceSpace('viewer');
        this.hitTestSource = await session.requestHitTestSource({ space: referenceSpace, entityTypes: ['plane'] });
        this.hitTestSourceRequested = true;
    }
}
