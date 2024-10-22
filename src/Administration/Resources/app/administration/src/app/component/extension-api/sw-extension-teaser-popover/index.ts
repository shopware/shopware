import type { PropType } from 'vue';
import { MtPopover } from '@shopware-ag/meteor-component-library';
import template from './sw-extension-teaser-popover.html.twig';
import './sw-extension-teaser-popover.scss';

interface TeaserPopoverConfig {
    positionId: string;
    src: string;
    component: string;
    props: {
        label?: string;
        locationId: string;
        icon?: string;
        variant?: string;
        locationTriggerId?: string;
    };
}

/**
 * @package customer-order
 *
 * @private
 * @description A teaser popover for upselling service only, no public usage
 * @example-type dynamic
 * @component-example
 * <sw-extension-teaser-popover position-identifier="my-special-position" />
 */
Shopware.Component.register('sw-extension-teaser-popover', {
    template,

    compatConfig: Shopware.compatConfig,

    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        'mt-popover': MtPopover,
    },

    props: {
        positionIdentifier: {
            type: String,
            required: true,
        },

        component: {
            type: Object as PropType<TeaserPopoverConfig>,
            required: false,
        },
    },

    data(): {
        isOpened: boolean;
        isMouseEnterTrigger: boolean;
        isMouseEnterContent: boolean;
        delay: number;
    } {
        return {
            isMouseEnterTrigger: false,
            isMouseEnterContent: false,
            isOpened: false,
            delay: 100,
        };
    },

    computed: {
        popoverComponent(): TeaserPopoverConfig {
            if (this.component) {
                return this.component;
            }

            return Shopware.Store.get('teaserPopover')?.identifier[this.positionIdentifier] || {};
        },

        isInsideComponent(): boolean {
            return this.isMouseEnterTrigger || this.isMouseEnterContent;
        },
    },

    methods: {
        onMouseEnterTrigger(): void {
            this.isMouseEnterTrigger = true;
        },

        onMouseEnterContent(): void {
            this.isMouseEnterContent = true;
        },

        onMouseLeaveContent(): void {
            setTimeout(() => {
                this.isMouseEnterContent = false;
            }, this.delay);
        },

        onMouseLeaveTrigger(): void {
            setTimeout(() => {
                this.isMouseEnterTrigger = false;
            }, this.delay);
        },
    },
});
