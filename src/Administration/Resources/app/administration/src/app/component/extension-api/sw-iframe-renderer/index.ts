import template from './sw-iframe-renderer.html.twig';
import type { Extension } from '../../../state/extensions.store';

/**
 * @package admin
 *
 * @private
 * @description This component renders iFrame views for extensions
 * @status ready
 * @example-type static
 * @component-example
 * <sw-iframe-renderer src="https://www.my-source.com" locationId="my-special-location" />
 */
Shopware.Component.register('sw-iframe-renderer', {
    template,

    inject: ['extensionSdkService'],

    props: {
        src: {
            type: String,
            required: true,
        },
        locationId: {
            type: String,
            required: true,
        },
    },

    data(): {
        heightHandler: null | (() => void),
        locationHeight: null | number,
        signedIframeSrc: null | string,
        } {
        return {
            // eslint-disable-next-line @typescript-eslint/no-empty-function
            heightHandler: null,
            locationHeight: null,
            signedIframeSrc: null,
        };
    },

    created() {
        this.heightHandler = Shopware.ExtensionAPI.handle('locationUpdateHeight', ({ height, locationId }) => {
            if (locationId === this.locationId) {
                this.locationHeight = Number(height) ?? null;
            }
        });
    },

    beforeDestroy() {
        if (this.heightHandler) {
            this.heightHandler();
        }
    },

    computed: {
        componentName(): string|undefined {
            return Shopware.State.get('sdkLocation').locations[this.locationId];
        },

        extension(): Extension | undefined {
            const extensions = Shopware.State.get('extensions');

            return Object.values(extensions).find((ext) => {
                return ext.baseUrl === this.src;
            });
        },

        extensionIsApp(): boolean {
            return this.extension?.type === 'app';
        },

        iFrameSrc(): string {
            const urlObject = new URL(this.src, window.location.origin);

            urlObject.searchParams.append('location-id', this.locationId);
            if (this.extension) {
                urlObject.searchParams.append('privileges', JSON.stringify(this.extension.permissions));
            }

            return urlObject.toString();
        },

        iFrameHeight(): string {
            if (typeof this.locationHeight === 'number') {
                return `${this.locationHeight}px`;
            }

            return '100%';
        },
    },

    watch: {
        extension: {
            immediate: true,
            handler(extension) {
                if (!extension || !this.extensionIsApp) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                this.extensionSdkService.signIframeSrc(extension.name, this.iFrameSrc).then((response) => {
                    const uri = (response as { uri?: string})?.uri;

                    if (!uri) {
                        return;
                    }

                    this.signedIframeSrc = uri;
                    // eslint-disable-next-line @typescript-eslint/no-empty-function
                }).catch(() => {});
            },
        },
    },
});
