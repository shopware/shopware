import type { Extension } from '../../../state/extensions.store';
import template from './sw-iframe-renderer.html.twig';
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

    compatConfig: Shopware.compatConfig,

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
        fullScreen: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        heightHandler: null | (() => void);
        urlHandler: null | (() => void);
        locationHeight: null | number;
        signedIframeSrc: null | string;
    } {
        return {
            heightHandler: null,
            urlHandler: null,
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

        this.urlHandler = Shopware.ExtensionAPI.handle(
            'locationUpdateUrl',
            async ({ hash, pathname, searchParams, locationId }) => {
                if (locationId !== this.locationId) {
                    return;
                }

                const filteredSearchParams = JSON.stringify(
                    searchParams.filter(([key]) => {
                        return ![
                            'location-id',
                            'privileges',
                            'shop-id',
                            'shop-url',
                            'timestamp',
                            'sw-version',
                            'sw-context-language',
                            'sw-user-language',
                            'shopware-shop-signature',
                        ].includes(key);
                    }),
                );

                await this.$router.replace({
                    query: {
                        [this.locationIdHashQueryKey]: hash,
                        [this.locationIdPathnameQueryKey]: pathname,
                        [this.locationIdSearchParamsQueryKey]: filteredSearchParams,
                    },
                });
            },
        );
    },

    beforeUnmount() {
        if (this.heightHandler) {
            this.heightHandler();
        }

        if (this.urlHandler) {
            this.urlHandler();
        }
    },

    computed: {
        locationIdHashQueryKey(): string {
            return `locationId_${this.locationId}_hash`;
        },

        locationIdPathnameQueryKey(): string {
            return `locationId_${this.locationId}_pathname`;
        },

        locationIdSearchParamsQueryKey(): string {
            return `locationId_${this.locationId}_searchParams`;
        },

        componentName(): string | undefined {
            return Shopware.State.get('sdkLocation').locations[this.locationId];
        },

        extension(): Extension | undefined {
            const extensions = Shopware.State.get('extensions');
            const srcWithoutSearchParameters = new URL(this.src).origin + new URL(this.src).pathname;

            return Object.values(extensions).find((ext) => {
                const extensionBaseUrlWithoutSearchParameters = new URL(ext.baseUrl).origin + new URL(ext.baseUrl).pathname;
                return extensionBaseUrlWithoutSearchParameters === srcWithoutSearchParameters;
            });
        },

        extensionIsApp(): boolean {
            return this.extension?.type === 'app';
        },

        iFrameSrc(): string {
            const urlObject = new URL(this.src, window.location.origin);
            urlObject.searchParams.append('location-id', this.locationId);

            return urlObject.toString();
        },

        iFrameHeight(): string {
            if (typeof this.locationHeight === 'number') {
                return `${this.locationHeight}px`;
            }

            return '100%';
        },

        classes(): { [key: string]: boolean } {
            return {
                'sw-iframe-renderer': true,
                'sw-iframe-renderer--full-screen': this.fullScreen,
            };
        },
    },

    watch: {
        extension: {
            immediate: true,
            handler() {
                this.signIframeSrc();
            },
        },
        locationId() {
            this.signIframeSrc();
        },
    },

    methods: {
        signIframeSrc() {
            if (!this.extension || !this.extensionIsApp) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
            this.extensionSdkService
                .signIframeSrc(this.extension.name, this.iFrameSrc)
                .then((response) => {
                    const uri = (response as { uri?: string })?.uri;

                    if (!uri) {
                        return;
                    }

                    // add information from query with hash, pathname and queries
                    const urlObject = new URL(uri);
                    const hash = this.$route.query[this.locationIdHashQueryKey];
                    const pathname = this.$route.query[this.locationIdPathnameQueryKey];
                    const searchParams = this.$route.query[this.locationIdSearchParamsQueryKey];

                    if (hash) {
                        urlObject.hash = hash as string;
                    }

                    if (pathname) {
                        urlObject.pathname = pathname as string;
                    }

                    if (searchParams) {
                        const parsedSearchParams = JSON.parse(searchParams as string) as [string, string][];

                        parsedSearchParams.forEach(
                            ([
                                key,
                                value,
                            ]) => {
                                urlObject.searchParams.append(key, value);
                            },
                        );
                    }

                    this.signedIframeSrc = urlObject.toString();
                    // eslint-disable-next-line @typescript-eslint/no-empty-function
                })
                .catch(() => {});
        },
    },
});
