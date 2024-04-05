import type { Object3D } from 'three';
import type { PerspectiveCamera, Scene, WebGLRenderer } from 'three';
import XrLighting from './webXr/XrLighting';
import ObjectPlacement from './webXr/ObjectPlacement';
import Overlay from './Overlay';
import type {XRTargetRaySpace} from 'three/src/renderers/webxr/WebXRController';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class WebXrView {

    private camera: PerspectiveCamera;
    private scene: Scene;
    private renderer: WebGLRenderer;

    private overlay: Overlay | null;
    private lighting: XrLighting;

    private controller: XRTargetRaySpace;
    private model: Object3D;

    private objectPlacement: ObjectPlacement;

    private session: XRSession;

    /**
     * opens a new webxr view
     * @param model
     * @param overlay
     */
    constructor(model: Object3D, overlay?: HTMLElement) {
        // add loading overlay
        this.overlay = overlay ? new Overlay(overlay) : null;

        // Setup camera
        // eslint-disable-next-line
        this.camera = new window.threeJs.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        this.camera.position.set(0, 1.6, 3);

        // setup scene
        // eslint-disable-next-line
        this.scene = new window.threeJs.Scene();
        this.model = model;
        this.model.visible = false;
        this.scene.add(this.model);

        // setup renderer
        // eslint-disable-next-line
        this.renderer = new window.threeJs.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.xr.enabled = true;
        document.body.appendChild(this.renderer.domElement);

        // setup overlay
        this.overlay?.removeExitListener(this.endSession.bind(this));
        this.overlay?.addExitListener(this.endSession.bind(this));

        // setup object placement
        this.objectPlacement = new ObjectPlacement(this.renderer, this.scene, this.model);
        this.lighting = new XrLighting(this.scene, this.renderer);
        this.controller = this.renderer.xr.getController(0);
        this.controller.addEventListener('select', this.objectPlacement.placeObject.bind(this.objectPlacement));

        // request session
        // eslint-disable-next-line @typescript-eslint/no-floating-promises
        navigator.xr.requestSession( 'immersive-ar', {
            requiredFeatures: ['local', 'hit-test'],
            optionalFeatures: ['light-estimation', 'local-floor', 'dom-overlay'],
            domOverlay: { root: this.overlay.element },
        } ).then( this.onSessionStarted.bind(this) );
    }

    /**
     * render loop
     * @param time
     * @param frame
     * @private
     */
    private render(time: DOMHighResTimeStamp, frame: XRFrame) {
        const hit = this.objectPlacement.update(frame);

        if (hit) {
            this.overlay?.trackingStarted();
        }

        this.renderer.render(this.scene, this.camera);
    }

    /**
     * end the session
     */
    public endSession() {
        // eslint-disable-next-line @typescript-eslint/no-floating-promises
        this.session.end().then();
    }

    /**
     * when the session starts we need to set up the xr settings and the animation loop
     * @param session
     */
    private async onSessionStarted(session: XRSession) {
        this.session = session;
        this.session.addEventListener( 'end', this.onSessionEnded.bind(this) );

        // setup XR
        const referenceSpaceType = this.session.enabledFeatures?.includes( 'local-floor' ) ? 'local-floor' : 'local';
        this.renderer.xr.setReferenceSpaceType( referenceSpaceType );
        await this.renderer.xr.setSession( this.session );

        // select best reference space
        const referenceSpace = this.renderer.xr.getReferenceSpace();
        referenceSpace.addEventListener( 'reset', this.objectPlacement.resetPlacement.bind(this.objectPlacement) );

        // start render loop
        this.renderer.setAnimationLoop(this.render.bind(this));

        this.overlay.sessionStarted();
    }

    /**
     * reset everything when the session ends
     */
    private onSessionEnded() {
        this.renderer.setAnimationLoop(null);
        this.objectPlacement.dispose();
        this.session.removeEventListener( 'end', this.onSessionEnded.bind(this));
        // eslint-disable-next-line @typescript-eslint/no-floating-promises
        this.session.end();
        this.overlay.sessionEnded();
        this.lighting.dispose();
    }
}
