/**
 * @sw-package framework
 */

import template from './sw-settings-logging-entry-info.html.twig';
import { featureService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [featureService],

    emits: ['close'],

    props: {
        logEntry: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'raw',
        };
    },

    computed: {
        entryInfoTabs() {
            return [
                {
                    label: this.$t('sw-settings-logging.entryInfo.tabRaw'),
                    name: 'raw',
                },
            ];
        },

        displayString() {
            return this.logEntry.context ? JSON.stringify(this.logEntry.context, null, 2) : '';
        },
    },

    methods: {
        onClose() {
            this.$emit('close');
        },
    },
};
