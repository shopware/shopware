/**
 * @sw-package framework
 */

import { classifyPlatform, formatShortcutKey } from 'src/core/helper/shortcut-key.helper';
import template from './sw-shortcut-overview-item.html.twig';
import './sw-shortcut-overview-item.scss';

/**
 * @private
 */
export default {
    template,

    inject: ['acl'],

    props: {
        title: {
            type: String,
            required: true,
        },
        content: {
            type: String,
            required: true,
        },
        privilege: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        showItem() {
            return this.acl.can(this.privilege);
        },

        platform() {
            const userPlatform = this.$device?.getPlatform?.() ?? window.navigator.platform;

            return classifyPlatform(userPlatform);
        },

        keys() {
            return this.content
                .split(' ')
                .flatMap((key) => key.split('-'))
                .filter(Boolean)
                .map((key) => formatShortcutKey(key, this.platform));
        },
    },
};
