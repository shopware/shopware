import type { ContentLayoutEntity } from '../../util/content-layout-repository.util';
import type {
    ScrollNavigationMode,
    ScrollNavigationPosition,
    ScrollNavigationSettings,
} from '../../util/scroll-navigation-settings.util';
import {
    pruneScrollNavigationAnchors,
    readScrollNavigationSettings,
    scrollNavigationSettingsPayload,
} from '../../util/scroll-navigation-settings.util';
import template from './sw-experience-studio-page-settings.html.twig';
import './sw-experience-studio-page-settings.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Object as PropType<ContentLayoutEntity | null>,
            required: false,
            default: null,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update-settings'],

    computed: {
        hasLayout(): boolean {
            return this.layout !== null;
        },

        scrollNavigation(): ScrollNavigationSettings {
            return readScrollNavigationSettings(this.layout);
        },

        anchorCount(): number {
            // Only anchors whose element still exists count; a stale entry is invisible in the tree.
            const pruned = pruneScrollNavigationAnchors(this.scrollNavigation, this.layout?.layout ?? []);

            return Object.keys(pruned.anchors).length;
        },

        showPositionOption(): boolean {
            return this.scrollNavigation.active && this.scrollNavigation.mode === 'sidebar';
        },

        modeOptions(): Array<{ value: ScrollNavigationMode; label: string }> {
            return [
                {
                    value: 'sidebar',
                    label: this.$t('sw-experience-studio.detail.pageSettings.scrollNavigation.modeSidebar'),
                },
                {
                    value: 'flat',
                    label: this.$t('sw-experience-studio.detail.pageSettings.scrollNavigation.modeFlat'),
                },
            ];
        },

        positionOptions(): Array<{ value: ScrollNavigationPosition; label: string }> {
            return [
                {
                    value: 'right',
                    label: this.$t('sw-experience-studio.detail.pageSettings.scrollNavigation.positionRight'),
                },
                {
                    value: 'left',
                    label: this.$t('sw-experience-studio.detail.pageSettings.scrollNavigation.positionLeft'),
                },
            ];
        },
    },

    methods: {
        onScrollNavigationActiveChange(active: boolean): void {
            this.emitScrollNavigation({ active: active === true });
        },

        onScrollNavigationModeChange(mode: unknown): void {
            this.emitScrollNavigation({ mode: mode === 'flat' ? 'flat' : 'sidebar' });
        },

        onScrollNavigationPositionChange(position: unknown): void {
            this.emitScrollNavigation({ position: position === 'left' ? 'left' : 'right' });
        },

        emitScrollNavigation(patch: Partial<ScrollNavigationSettings>): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit(
                'update-settings',
                scrollNavigationSettingsPayload({
                    ...this.scrollNavigation,
                    ...patch,
                }),
            );
        },
    },
});
