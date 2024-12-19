/*
 * @package storefront
 *
 * @experimental stableVersion:v6.8.0 feature:SPECULATION_RULES
 */

import Plugin from 'src/plugin-system/plugin.class';

export default class SpeculationRulesPlugin extends Plugin {

    static options = {
        selectorLogoLink: '.header-logo-main-link',
        selectorMainNavigationLinks: '.main-navigation-link',
        selectorNavigationLinks: '.nav-item.nav-link',
        selectorProducts: ['.product-image-link', '.product-name', '.btn-detail'],
    };

    init() {
        if (
            HTMLScriptElement.supports &&
            HTMLScriptElement.supports('speculationrules')
        ) {
            this._removeSpeculationRulesScriptTag();
            const rules = this.customizeRules([
                ...this._getProductSpeculationRules(),
                ...this._getTopNavigationSpeculationRules(),
            ]);
            this._addSpeculationRulesScriptTag(rules);
        }
    }

    /**
     * @private
     */
    _removeSpeculationRulesScriptTag() {
        if (this.speculationRulesScriptTag) {
            document.head.removeChild(this.speculationRulesScriptTag);
        }
    }

    /**
     * @param rules
     * @returns []
     */
    customizeRules(rules) {
        return rules;
    }

    /**
     * @returns {[{source: string, where: {and: [{href_matches: string},{selector_matches: *}]}, eagerness: string}]}
     * @private
     */
    _getProductSpeculationRules() {
        return [
            {
                source: 'document',
                where: {
                    and: [
                        { href_matches: `${window.location.origin}/*` },
                        { selector_matches: this.options.selectorProducts },
                    ],
                },
                eagerness: 'moderate',
            },
        ];
    }

    /**
     * @returns {[{source: string, where: {and: [{href_matches: string},{selector_matches: *[]}]}, eagerness: string}]}
     * @private
     */
    _getTopNavigationSpeculationRules() {
        return [
            {
                source: 'document',
                where: {
                    and: [
                        { href_matches: `${window.location.origin}/*` },
                        {
                            selector_matches: [
                                this.options.selectorMainNavigationLinks,
                                this.options.selectorNavigationLinks,
                                this.options.selectorLogoLink,
                            ],
                        },
                    ],
                },
                eagerness: 'moderate',
            },
        ];
    }

    /**
     * @param preRenderRules
     * @private
     */
    _addSpeculationRulesScriptTag(preRenderRules) {
        if (this.speculationRulesScriptTag) {
            document.head.removeChild(this.speculationRulesScriptTag);
        }

        this.speculationRulesScriptTag = document.createElement('script');
        this.speculationRulesScriptTag.type = 'speculationrules';
        this.speculationRulesScriptTag.innerHTML = JSON.stringify({ prerender: preRenderRules });

        document.head.appendChild(this.speculationRulesScriptTag);
    }
}
