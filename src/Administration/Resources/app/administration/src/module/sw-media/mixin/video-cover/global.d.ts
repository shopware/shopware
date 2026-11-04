/**
 * @sw-package discovery
 */
import type VideoCoverMixin from './private';

declare global {
    interface MixinContainer {
        'video-cover': typeof VideoCoverMixin;
    }
}
