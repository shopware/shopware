import HitTest from './HitTest';
import type { Object3D, Scene, WebGLRenderer, Raycaster } from 'three';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class ObjectPlacement {

    private renderer: WebGLRenderer;
    private scene: Scene;
    private model: Object3D;

    private webXrHitTest: HitTest;
    private placed: boolean;

    private selectedObject: Object3D | null;
    private raycaster: Raycaster;

    constructor(renderer: WebGLRenderer, scene: Scene, model: Object3D) {
        this.renderer = renderer;
        this.scene = scene;
        this.model = model;

        this.model.visible = false;
        this.placed = false;

        this.selectedObject = null;

        this.webXrHitTest = new HitTest(this.renderer, this.scene);

        // eslint-disable-next-line
        this.raycaster = new window.threeJs.Raycaster();
    }

    /**
     * returns a boolean if it hits something
     * @param frame
     */
    public update(frame: XRFrame): boolean {
        return this.webXrHitTest.update(frame);
    }

    public placeObject() {
        const newPose = this.webXrHitTest.getHitPose();
        if (newPose) {
            this.model.position.setFromMatrixPosition(newPose);
            this.model.visible = true;
            this.placed = true;
            this.webXrHitTest.hideMarker();
        }
    }

    public resetPlacement() {
        this.model.visible = false;
        this.webXrHitTest.showMarker();
    }

    public dispose() {
        this.webXrHitTest.dispose();
    }
}
