import template from './sw-progress-bar.html.twig';
import './sw-progress-bar.scss';

/**
 * @public
 * @description Renders a progressbar to indicate progress
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-progress-bar :value="0" :maxValue="480"></sw-progress-bar>
 */
export default {
    name: 'sw-progress-bar',

    template,

    props: {
        value: {
            type: Number,
            default: 0
        },
        maxValue: {
            type: Number,
            default: 100,
            required: false
        }
    },

    computed: {
        styleWidth() {
            let percentage = this.value / this.maxValue * 100;
            if (percentage > 100) {
                percentage = 100;
            }

            if (percentage < 0) {
                percentage = 0;
            }

            return `${percentage}%`;
        },

        progressClasses() {
            return {
                'is--empty': this.value < 1
            };
        }
    }
};
