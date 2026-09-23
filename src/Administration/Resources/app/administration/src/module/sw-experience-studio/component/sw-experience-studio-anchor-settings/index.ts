import type { ContentLayoutEntity } from '../../util/content-layout-repository.util';
import type { ScrollNavigationAnchor, ScrollNavigationSettings } from '../../util/scroll-navigation-settings.util';
import { readScrollNavigationSettings, scrollNavigationSettingsPayload } from '../../util/scroll-navigation-settings.util';
import template from './sw-experience-studio-anchor-settings.html.twig';
import './sw-experience-studio-anchor-settings.scss';

/**
 * The per-element half of the scroll navigation: any selected element can become an anchor, stored on the
 * layout's page settings rather than on the element's type-bound properties.
 *
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
        elementId: {
            type: String,
            required: true,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update-settings'],

    computed: {
        scrollNavigation(): ScrollNavigationSettings {
            return readScrollNavigationSettings(this.layout);
        },

        anchor(): ScrollNavigationAnchor | null {
            return this.scrollNavigation.anchors[this.elementId] ?? null;
        },

        isAnchor(): boolean {
            return this.anchor !== null;
        },

        anchorLabel(): string {
            return this.anchor?.label ?? '';
        },

        isNavigationInactive(): boolean {
            return this.isAnchor && !this.scrollNavigation.active;
        },
    },

    methods: {
        onAnchorToggle(enabled: boolean): void {
            const anchors = { ...this.scrollNavigation.anchors };

            if (enabled) {
                anchors[this.elementId] = { label: this.anchorLabel };
            } else {
                delete anchors[this.elementId];
            }

            this.emitAnchors(anchors);
        },

        onAnchorLabelChange(label: unknown): void {
            if (!this.isAnchor) {
                return;
            }

            this.emitAnchors({
                ...this.scrollNavigation.anchors,
                [this.elementId]: { label: typeof label === 'string' ? label : '' },
            });
        },

        emitAnchors(anchors: Record<string, ScrollNavigationAnchor>): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit(
                'update-settings',
                scrollNavigationSettingsPayload({
                    ...this.scrollNavigation,
                    anchors,
                }),
            );
        },
    },
});
