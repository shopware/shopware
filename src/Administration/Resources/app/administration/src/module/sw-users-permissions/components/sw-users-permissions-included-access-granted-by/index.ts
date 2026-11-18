/**
 * @sw-package fundamentals@framework
 */
import { reactive } from 'vue';
import type { GrantedBy } from '../sw-users-permissions-included-access-modal';
import template from './sw-users-permissions-included-access-granted-by.html.twig';
import './sw-users-permissions-included-access-granted-by.scss';

const OPEN_DELAY = 150;
const CLOSE_DELAY = 100;

/**
 * Shared by all instances, so opening one popover closes the others.
 */
const popover = reactive<{ openId: string | null; isPinned: boolean }>({
    openId: null,
    isPinned: false,
});

/**
 * Lists the permissions that grant the access of an included area in a popover.
 * Hover and focus open it, a click pins it open until the next click, an outside click or Escape.
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        popoverId: {
            type: String,
            required: true,
        },

        grantedBy: {
            type: Array as PropType<GrantedBy[]>,
            required: true,
        },
    },

    data(): { timer: number | null } {
        return {
            timer: null,
        };
    },

    computed: {
        isOpen(): boolean {
            return popover.openId === this.popoverId;
        },

        descriptionId(): string {
            return `sw-users-permissions-included-access-granted-by-${this.popoverId}`;
        },

        description(): string {
            return this.grantedBy.map((grant) => (grant.roles ? `${grant.label}: ${grant.roles}` : grant.label)).join('; ');
        },
    },

    beforeUnmount() {
        this.clearTimer();

        if (this.isOpen) {
            popover.openId = null;
            popover.isPinned = false;
        }
    },

    methods: {
        clearTimer(): void {
            if (this.timer !== null) {
                window.clearTimeout(this.timer);
                this.timer = null;
            }
        },

        onMouseEnter(): void {
            this.clearTimer();

            if (popover.isPinned && !this.isOpen) {
                return;
            }

            this.timer = window.setTimeout(
                () => {
                    popover.openId = this.popoverId;
                },
                popover.openId ? 0 : OPEN_DELAY,
            );
        },

        onLeave(): void {
            this.clearTimer();

            if (popover.isPinned || !this.isOpen) {
                return;
            }

            this.timer = window.setTimeout(() => {
                if (!popover.isPinned && this.isOpen) {
                    popover.openId = null;
                }
            }, CLOSE_DELAY);
        },

        onFocus(): void {
            if (popover.isPinned && !this.isOpen) {
                return;
            }

            this.clearTimer();
            popover.openId = this.popoverId;
        },

        onClick(): void {
            this.clearTimer();

            if (popover.isPinned && this.isOpen) {
                this.close();
                return;
            }

            popover.openId = this.popoverId;
            popover.isPinned = true;
        },

        close(): void {
            if (!this.isOpen) {
                return;
            }

            this.clearTimer();
            popover.openId = null;
            popover.isPinned = false;
        },
    },
});
