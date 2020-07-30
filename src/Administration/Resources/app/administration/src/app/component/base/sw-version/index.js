import template from './sw-version.html.twig';
import './sw-version.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Shows the header in the administration main menu
 * @status ready
 * @example-type static
 * @component-example
 * <div style="background: linear-gradient(to bottom, #303A4F, #2A3345); padding: 30px;">
 *     <sw-version class="collapsible-text"></sw-version>
 * </div>
 */
Component.register('sw-version', {
    template,

    computed: {
        version() {
            const version = Shopware.Context.app.config.version;
            const match = version.match(/(\d+\.?\d+\.?\d+?\.?\d+?)-?([a-z]+)?(\d+(.\d+)*)?/i);

            if (match === null) {
                return version;
            }

            let output = `v${match[1]}`;

            if (match[2]) {
                output += ` ${this.getHumanReadableText(match[2])}`;
            } else {
                output += ' Stable Version';
            }

            if (match[3]) {
                output += ` ${match[3]}`;
            }

            return output;
        }
    },

    methods: {
        getHumanReadableText(text) {
            if (text === 'dp') {
                return 'Developer Preview';
            }

            if (text === 'rc') {
                return 'Release Candidate';
            }

            if (text === 'dev') {
                return 'Developer Version';
            }

            if (text === 'ea') {
                return 'Early Access';
            }

            return text;
        }
    }
});
