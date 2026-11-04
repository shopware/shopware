/**
 * @sw-package discovery
 */
import type MediaGridListenerMixin from './private';

declare global {
    interface MixinContainer {
        'media-grid-listener': typeof MediaGridListenerMixin;
    }
}
