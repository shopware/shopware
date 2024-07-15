/**
 * @package admin
 */

import template from './sw-rating-stars.html.twig';
import './sw-rating-stars.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Renders rating stars
 * @status ready
 * @example-type static
 * @component-example
 * <sw-rating-stars v-model='actualStars' :maxStars='5' :iconSize='16' :displayFractions='4'></sw-rating-stars>
 */
Component.register('sw-rating-stars', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        value: {
            type: Number,
            required: true,
        },

        maxStars: {
            type: Number,
            required: false,
            default: 5,
        },

        iconSize: {
            type: Number,
            required: false,
            default: 16,
        },

        displayFractions: {
            type: Number,
            required: false,
            default: 4,
            validator(value) {
                return value > 0 && value <= 100;
            },
        },
    },

    computed: {
        ratingTooltip() {
            return {
                message: this.$tc('sw-rating-stars.ratingTooltipText', 0, {
                    actual: this.cappedValue,
                    max: this.maxStars,
                }),
            };
        },

        cappedValue() {
            return Math.min(this.value, this.maxStars);
        },

        partialStarCutStyle() {
            const negatedPartialValue = 1 - (this.value % 1);
            const percentage = (Math.round(negatedPartialValue * this.displayFractions) * 100) / this.displayFractions;

            // Adjusting styles to make the changes more visible
            let stylePercentage = percentage;
            if (percentage >= 25 && percentage < 50) {
                stylePercentage += 10;
            } else if (percentage <= 75 && percentage > 50) {
                stylePercentage -= 10;
            }

            return `clip-path: inset(0 ${stylePercentage}% 0 0)`;
        },

        dynamicWidthStyle() {
            return `width: ${this.maxStars * this.iconSize + this.maxStars - 1}px;`;
        },
    },
});
