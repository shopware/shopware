import template from './sw-card-section.html.twig';
import './sw-card-section.scss';

const { Component } = Shopware;

/**
 * @public
 * @description A container component which separates the content of <code>sw-card</code> into multiple sections.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-card title="Split card view" large>
 *     <sw-container columns="1fr 1fr">
 *         <sw-card-section divider="right">
 *             bar
 *         </sw-card-section>
 *         <sw-card-section>
 *             foo
 *         </sw-card-section>
 *     </sw-container>
 * </sw-card>
 */
Component.register('sw-card-section', {
    template,

    props: {
        divider: {
            type: String,
            required: false,
            default: '',
            validValues: ['top', 'right', 'bottom', 'left'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['top', 'right', 'bottom', 'left'].includes(value);
            },
        },
        secondary: {
            type: Boolean,
            required: false,
            default: false,
        },
        slim: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        cardSectionClasses() {
            return {
                [`sw-card-section--divider-${this.divider}`]: this.divider,
                'sw-card-section--secondary': this.secondary,
                'sw-card-section--slim': this.slim,
            };
        },
    },
});
