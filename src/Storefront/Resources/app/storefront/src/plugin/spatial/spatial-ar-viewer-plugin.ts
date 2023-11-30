import { type Object3D } from 'three';
import SpatialObjectLoaderUtil from './utils/spatial-object-loader-util';
import iosQuickLook from './utils/ar/iosQuickLook';
import Plugin from 'src/plugin-system/plugin.class';
import { supportQuickLook, supportsAr, supportWebXR } from './utils/ar/arSupportChecker';
import WebXrView from './utils/ar/WebXrView';
import { loadThreeJs } from './utils/spatial-threejs-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class SpatialArViewerPlugin extends Plugin {
    private modelUrl: string;

    private objectLoader: SpatialObjectLoaderUtil;

    private model: Object3D;

    private el: HTMLElement;

    private supportsAr: boolean;

    async init() {
        await loadThreeJs();

        this.modelUrl = this.el.dataset.spatialModelUrl;
        this.supportsAr = await supportsAr();

        if (!this.modelUrl) {
            return;
        }

        this.objectLoader = new SpatialObjectLoaderUtil();

        // eslint-disable-next-line @typescript-eslint/no-floating-promises
        this.objectLoader.loadSingleObjectByUrl(this.modelUrl, {
            center: true,
            clampSize: false,
        }).then((model) => {
            this.model = model;
            this.onReady();
        });

        this.el.addEventListener('click', () => {
            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.startARView().then();
        });
    }

    public async startARView(): Promise<void> {
        if (!this.model || !this.supportsAr) {
            const qrModalTemplate = document.querySelector('.ar-qr-modal');
            if (qrModalTemplate) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
                new bootstrap.Modal(qrModalTemplate).show();
            }
            return;
        }

        if (await supportWebXR()) {
            this.startWebXRView();
            return;
        }
        if (supportQuickLook()) {
            this.startIOSQuickLook();
            return;
        }
    }

    private startIOSQuickLook(): void {
        // eslint-disable-next-line @typescript-eslint/no-floating-promises
        iosQuickLook(this.model).then();
    }

    private startWebXRView(): void {
        const overlay = this.el.parentElement.querySelector('[data-spatial-ar-overlay]') as HTMLElement;
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const webXrView = new WebXrView(this.model, overlay);
    }

    private onReady(): void {
        this.el.classList.add('spatial-ar-ready');
    }
}
