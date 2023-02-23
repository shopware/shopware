import template from './sw-iframe-renderer.html.twig';
import type { Extension } from '../../../state/extensions.store';
import './sw-iframe-renderer.scss';

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
        iFrameSrcResult: null | string,
        } {
        return {
            // eslint-disable-next-line @typescript-eslint/no-empty-function
            heightHandler: null,
            locationHeight: null,
            iFrameSrcResult: null,
        };
    },

    created() {
        this.heightHandler = Shopware.ExtensionAPI.handle('locationUpdateHeight', ({ height, locationId }) => {
            if (locationId === this.locationId) {
                this.locationHeight = Number(height) ?? null;
            }
        });
        this.loadIframeSrc();
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
        locationId() {
            this.loadIframeSrc();
        },
    },

    methods: {
        loadIframeSrc() {
            if (!this.extension || !this.extensionIsApp) {
                this.iFrameSrcResult = this.iFrameSrc;
                return;
            }
            void this.extensionSdkService.signIframeSrc(this.extension.name, this.iFrameSrc).then((response) => {
                const uri = (response as { uri?: string})?.uri;

                if (!uri) {
                    return;
                }

                this.iFrameSrcResult = uri;
            });
        },
    },
});
