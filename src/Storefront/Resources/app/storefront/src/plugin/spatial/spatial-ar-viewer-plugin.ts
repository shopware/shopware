import Plugin from 'src/plugin-system/plugin.class';
import { loadDIVE } from './utils/spatial-dive-load-util';
import type NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export default class SpatialArViewerPlugin extends Plugin {
    private modelUrl: string;
    private spatialArId: string;

    private el: HTMLElement;

    public static options: object;

    $emitter: NativeEventEmitter;

    async init() {
        await loadDIVE();

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
        this.modelUrl = this.options.modelUrl;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
        this.spatialArId = this.options.spatialArId;

        if (!this.modelUrl) {
            return;
        }

        this.onReady();

        this.el.addEventListener('click', () => {
            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.startARView().then();
        });
    }

    public async startARView(): Promise<void> {
        // launch the preloaded ARSystem from @shopware-ag/dive using async/await and try/catch
        try {
            await window.ARSystem.launch(this.modelUrl, {
                arPlacement: 'horizontal', // only place on horizontal surfaces
                arScale: 'auto', // make model scalable
            });
        } catch {
            this.showARUnsupportedModal(this.spatialArId);
        }
    }

    private onReady(): void {
        this.el.classList.add('spatial-ar-ready');
        const qrParams = new URLSearchParams(window.location.search);
        if (!qrParams.has('autostartAr') || !this.spatialArId || qrParams.get('autostartAr') !== this.spatialArId) {
            return;
        }

        if (window.autostartingARView === null) {
            window.autostartingARView = true;
            this.showARAutostartModal(this.spatialArId);
        }
    }

    private showARUnsupportedModal(spatialArId: string): void {
        const qrModalTemplate = this.getARUnsupportedModalTemplate(spatialArId);
        if (!qrModalTemplate) return;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        window.focusHandler.saveFocusState('spatial-ar-viewer');

        const refocusButton = () => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            window.focusHandler.resumeFocusState('spatial-ar-viewer');
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            modal._element.removeEventListener('hidden.bs.modal', refocusButton);
        };

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        const modal = new bootstrap.Modal(qrModalTemplate);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        modal._element.addEventListener('hidden.bs.modal', refocusButton);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        modal.show();
    }

    private getARUnsupportedModalTemplate(spatialArId: string): HTMLElement | null {
        let qrModalTemplate;
        if (spatialArId) {
            qrModalTemplate = document.querySelector(
                `.ar-qr-modal [data-ar-model-id='${spatialArId}']`
            )?.closest('.ar-qr-modal') as HTMLElement | null;
        } else {
            // eslint-disable-next-line @typescript-eslint/no-unnecessary-type-assertion
            qrModalTemplate = document.querySelector('.ar-qr-modal') as HTMLElement | null;
        }
        qrModalTemplate?.closest('body')?.appendChild(qrModalTemplate);
        return qrModalTemplate;
    }

    private showARAutostartModal(spatialArId: string): void {
        const qrModalOpenArSession = document.querySelector(
            `.ar-qr-modal-open-session [data-modal-open-ar-session-autostart='${spatialArId}']`
        )?.closest('.ar-qr-modal-open-session');

        if (!qrModalOpenArSession) {
            return;
        }

        qrModalOpenArSession.getElementsByClassName('ar-btn-open-session')[0]?.addEventListener('click', () => {
            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.startARView().then();
        });
        qrModalOpenArSession?.closest('body')?.appendChild(qrModalOpenArSession);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        new bootstrap.Modal(qrModalOpenArSession).show();
    }
}
