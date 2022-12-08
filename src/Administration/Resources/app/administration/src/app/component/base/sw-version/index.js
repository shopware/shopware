/**
 * @package admin
 */

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
            let output = '';
            const version = Shopware.Context.app.config.version;

            // https://regex101.com/r/oRuJjS/1
            const match = version.match(/(\d+)\.?(\d+)\.?(\d+)?\.?(\d+)?-?([a-z]+)?(\d+(.\d+)*)?/i);

            if (match === null) {
                return version;
            }

            // Get rid of whole regex match for example "6.4.99999.9999999-dev"
            match.shift();

            // Iterate version parts and append to output
            match.forEach(((versionPart, index) => {
                if (typeof versionPart !== 'string') {
                    return;
                }

                const hrt = this.getHumanReadableText(versionPart);

                if (hrt !== versionPart) {
                    output += ` ${hrt}`;

                    return;
                }

                // Special case for the first version part. Don't append a dot to the string
                if (index === 0) {
                    output += `${hrt}`;

                    return;
                }

                // Add dot and version part to output
                output += `.${hrt}`;
            }));

            return output;
        },
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
        },
    },
});
