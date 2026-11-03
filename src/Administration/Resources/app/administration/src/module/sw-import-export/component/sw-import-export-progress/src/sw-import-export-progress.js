/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export-progress.html.twig';
import './sw-import-export-progress.scss';
import { featureService } from '../dependencies';

/**
 * @private
 */
export default {
    template,

    inject: [featureService],

    emits: ['process-start', 'process-start-dryrun'],

    props: {
        activityType: {
            type: String,
            required: false,
            default: 'import',
            validValues: ['import', 'export'],
            validator(value) {
                return ['import', 'export'].includes(value);
            },
        },

        disableButton: {
            type: Boolean,
            required: false,
            default: true,
        },
    },
};
