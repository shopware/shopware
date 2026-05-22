import 'src/app/component/base/sw-collapse';
import template from './sw-media-collapse.html.twig';
import './sw-media-collapse.scss';

const MEDIA_LIBRARY_PREFERENCES_KEY = 'media.library.preferences';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        userConfigService: {
            default: null,
        },
    },

    props: {
        expandOnLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        title: {
            type: String,
            required: true,
        },

        storageKey: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            expanded: this.expandOnLoading,
        };
    },

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },
        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadExpandedState();
        },

        collapseItem() {
            this.expanded = !this.expanded;
            this.saveExpandedState();
        },

        async loadExpandedState() {
            if (!this.storageKey || !this.userConfigService?.search) {
                return;
            }

            try {
                const response = await this.userConfigService.search([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const preferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY];
                const expanded = preferences?.sidebarSections?.[this.storageKey];

                if (typeof expanded === 'boolean') {
                    this.expanded = expanded;
                }
            } catch {
                this.expanded = this.expandOnLoading;
            }
        },

        async saveExpandedState() {
            if (!this.storageKey || !this.userConfigService?.upsert) {
                return;
            }

            try {
                const response = await this.userConfigService.search?.([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const preferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY] ?? {};

                await this.userConfigService.upsert({
                    [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                        ...preferences,
                        sidebarSections: {
                            ...preferences.sidebarSections,
                            [this.storageKey]: this.expanded,
                        },
                    },
                });
            } catch {
                // Keep the sidebar usable when user preferences can not be saved.
            }
        },
    },
};
