import Plugin from 'src/plugin-system/plugin.class';
import { loadDIVE } from './utils/spatial-dive-load-util';
import type NativeEventEmitter from 'src/helper/emitter.helper';

declare global {
    interface Window {
        autostartingARView: boolean | undefined;
        focusHandler: {
            saveFocusState: (state: string) => void;
            resumeFocusState: (state: string) => void;
        };
    }
    const bootstrap: {
        Modal: new (element: Element) => {
            _element: Element;
            show: () => void;
        };
    };
}

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export default class SpatialArViewerPlugin extends Plugin {
    /**
     * The element that contains the AR button.
     * @type {HTMLElement}
     */
    private el!: HTMLElement;

    public options!: {
        modelUrl: string;
        spatialArId: string;
        snippets: {
            openArView: string;
            launchingArView: string;
            arErrors: {
                desktopNotSupported: {
                    reason: string;
                    solution: string;
                };
                notSafariOnIOS: {
                    reason: string;
                    solution: string;
                };
                IOSVersionMismatch: {
                    reason: string;
                    solution: string;
                };
                unknownIOSError: {
                    reason: string;
                    solution: string;
                };
                unknownARError: {
                    reason: string;
                    solution: string;
                };
            };
        };
    };

    $emitter: NativeEventEmitter;

    /**
     * `qrModalAutostartARButton` will only be set if the page was entered with the `autostartAr` parameter.
     * The page can be entered with the `autostartAr` parameter by scanning a QR code (from `qrModalOnError`) when an error occurs while launching AR.
     * If the page was entered normally, `qrModalAutostartARButton` will remain null.
     * @type {Element | null}
     */
    private autostartArModalButton: Element | null = null;

    private arSystem: InstanceType<typeof window.DIVEARPlugin.ARSystem> | null = null;

    private launchingAR = false;

    private modelUrl = '';
    private spatialArId = '';

    async init() {
        await loadDIVE();

        this.arSystem = new window.DIVEARPlugin.ARSystem();
        this.modelUrl = this.options.modelUrl;
        this.spatialArId = this.options.spatialArId;

        if (!this.modelUrl) {
            return;
        }

        this.onReady();

        this.el.addEventListener('click', () => {
            void this.startARViewAsync();
        });
    }

    public async startARViewAsync(): Promise<void> {
        const arButtonText = this.el.querySelector('#ar-button-text') ;
        const autostartArModalButtonText = this.autostartArModalButton?.querySelector('#ar-btn-open-session-text') as HTMLSpanElement | null;

        // launch the preloaded ARSystem from @shopware-ag/dive using async/await and try/catch
        try {
            if (!this.arSystem) {
                throw new Error('ARSystem not loaded');
            }

            if (this.launchingAR) {
                return;
            }

            this.launchingAR = true;

            // set the button text to the launching text
            if (arButtonText) {
                arButtonText.textContent = this.options.snippets.launchingArView;
            }

            if (autostartArModalButtonText) {
                autostartArModalButtonText.textContent = this.options.snippets.launchingArView;
            }

            await this.arSystem.launch(this.modelUrl, {
                arPlacement: 'horizontal', // only place on horizontal surfaces
                arScale: 'auto', // make model scalable
            });
        } catch (error: unknown) {
            this.handleError(this.spatialArId, error);
        } finally {
            // reset the button text to the original text
            if (arButtonText) {
                arButtonText.textContent = this.options.snippets.openArView;
            }

            if (autostartArModalButtonText) {
                autostartArModalButtonText.textContent = this.options.snippets.openArView;
            }

            this.launchingAR = false;
        }
    }

    private onReady(): void {
        this.el.classList.add('spatial-ar-ready');
        const qrParams = new URLSearchParams(window.location.search);

        if (!qrParams.has('autostartAr') || !this.spatialArId || qrParams.get('autostartAr') !== this.spatialArId) {
            return;
        }

        if (!window.autostartingARView) {
            window.autostartingARView = true;
            this.showARAutostartModal(this.spatialArId);

            // start the AR view immediately after the autostart modal is shown
            void this.startARViewAsync();
        }
    }

    private handleError(spatialArId: string, error: unknown): void {
        // if the error is not an ARError, we don't know how to handle it
        if (!(error instanceof window.DIVEARPlugin.ARError)) {
            console.error('spatial-ar-viewer-plugin.ts: Spatial AR Viewer Plugin error:', error);
            return;
        }

        // find the modal template in the document
        const qrModalOnError = this.getARUnsupportedModal(spatialArId);
        if (!qrModalOnError) {
            console.error('spatial-ar-viewer-plugin.ts: Twig structure error: ARUnsupportedModalTemplate not found in document');
            return;
        }

        // search for reason and solution spans to append in the modal
        const errorReason = qrModalOnError.querySelector('#ar-qr-modal-error-reason') ;
        const errorSolution = qrModalOnError.querySelector('#ar-qr-modal-error-solution') ;

        // if the reason or solution span is not found, log an error and return
        if (!errorReason) {
            console.error('spatial-ar-viewer-plugin.ts: Twig structure error: "#ar-qr-modal-error-reason not found in ARUnsupportedModalTemplate');
        }
        if (!errorSolution) {
            console.error('spatial-ar-viewer-plugin.ts: Twig structure error: "#ar-qr-modal-error-solution not found in ARUnsupportedModalTemplate');
        }
        if (!errorReason || !errorSolution) {
            return;
        }

        if (error instanceof window.DIVEARPlugin.ARDesktopPlatformError) {
            /**
             * @description
             * The user is on a desktop device.
             */
            errorReason.textContent = this.options.snippets.arErrors.desktopNotSupported.reason;
            errorSolution.textContent = this.options.snippets.arErrors.desktopNotSupported.solution;
            console.error('spatial-ar-viewer-plugin.ts:', 'ErrorType: ' + error.type, 'ErrorMessage: ' + error.message);
        } else if (error instanceof window.DIVEARPlugin.ARQuickLookNotSafariError) {
            /**
             * @description
             * The user is not using Safari on iOS.
             */
            errorReason.textContent = this.options.snippets.arErrors.notSafariOnIOS.reason;
            errorSolution.textContent = this.options.snippets.arErrors.notSafariOnIOS.solution;
            console.error('spatial-ar-viewer-plugin.ts:', 'ErrorType: ' + error.type, 'ErrorMessage: ' + error.message);
        } else if (error instanceof window.DIVEARPlugin.ARQuickLookVersionMismatchError) {
            /**
             * @description
             * The user is using an iOS version below 12.0.
             */
            errorReason.textContent = this.options.snippets.arErrors.IOSVersionMismatch.reason;
            errorSolution.textContent = this.options.snippets.arErrors.IOSVersionMismatch.solution;
            console.error('spatial-ar-viewer-plugin.ts:', 'ErrorType: ' + error.type, 'ErrorMessage: ' + error.message);
        } else if (error instanceof window.DIVEARPlugin.ARQuickLookUnknownError) {
            /**
             * @description
             * An unknown iOS-related error occurs.
             * (iOS version not found, ARKit not supported even if it should, etc.)
             * This is an edge case and should not happen.
             * If it does, we should add a new error type to DIVEARPlugin and handle it here.
            */
            errorReason.textContent = this.options.snippets.arErrors.unknownIOSError.reason;
            errorSolution.textContent = this.options.snippets.arErrors.unknownIOSError.solution;
            console.error('spatial-ar-viewer-plugin.ts:', 'ErrorType: ' + error.type, 'ErrorMessage: ' + error.message);
        } else {
            /**
             * @description
             * We introduce a new error type in DIVEARPlugin that we don't handle yet.
             * This is an edge case and should not happen.
             * If it does, we have to handle it here accordingly.
            */
            errorReason.textContent = this.options.snippets.arErrors.unknownARError.reason;
            errorSolution.textContent = this.options.snippets.arErrors.unknownARError.solution;
            console.error('spatial-ar-viewer-plugin.ts: Unknown DIVEARPlugin error:', 'ErrorType: ' + error.type, 'ErrorMessage: ' + error.message);
        }

        // show the modal
        this.showARUnsupportedModal(qrModalOnError);
    }

    private showARUnsupportedModal(qrModal: HTMLElement): void {
        window.focusHandler.saveFocusState('spatial-ar-viewer');

        const refocusButton = () => {
            window.focusHandler.resumeFocusState('spatial-ar-viewer');
            modal._element.removeEventListener('hidden.bs.modal', refocusButton);
        };

        const modal = new bootstrap.Modal(qrModal);
        modal._element.addEventListener('hidden.bs.modal', refocusButton);
        modal.show();
    }

    private getARUnsupportedModal(spatialArId: string): HTMLElement | null {
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
        const autostartArModal = document.querySelector('.ar-qr-modal-open-session');
        if (!autostartArModal) {
            return;
        }

        const autostartArModalButton = autostartArModal.querySelector(
            `[data-modal-open-ar-session-autostart='${spatialArId}']`
        );

        if (!autostartArModalButton) {
            return;
        }

        this.autostartArModalButton = autostartArModalButton;

        this.autostartArModalButton.addEventListener('click', () => {
            void this.startARViewAsync();
        });

        autostartArModal.closest('body')?.appendChild(autostartArModal);
        new bootstrap.Modal(autostartArModal).show();
    }
}
