/**
 * @sw-package discovery
 */
import type MediaSidebarModalMixin from './private';

declare global {
    interface MixinContainer {
        'media-sidebar-modal-mixin': typeof MediaSidebarModalMixin;
    }
}
