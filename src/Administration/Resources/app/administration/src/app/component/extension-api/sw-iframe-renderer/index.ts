import type { extension } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';
import template from './sw-iframe-renderer.html.twig';

/**
 * @private
 * @description This component renders iFrame views for extensions
 * @status ready
 * @example-type static
 * @component-example
 * <sw-iframe-renderer src="https://www.my-source.com" locationId="my-special-location" />
 */
Shopware.Component.register('sw-iframe-renderer', {
    template,

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
        } {
        return {
            // eslint-disable-next-line @typescript-eslint/no-empty-function
            heightHandler: null,
            locationHeight: null,
        };
    },

    created() {
        this.heightHandler = Shopware.ExtensionAPI.handle('locationUpdateHeight', ({ height, locationId }) => {
            if (locationId === this.locationId) {
                this.locationHeight = height ?? null;
            }
        });
    },

    beforeDestroy() {
        if (this.heightHandler) {
            this.heightHandler();
        }
    },

    computed: {
        extension(): extension | undefined {
            const extensions = Shopware.State.get('extensions');

            return Object.values(extensions).find((ext) => {
                return ext.baseUrl === this.src;
            });
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
});
