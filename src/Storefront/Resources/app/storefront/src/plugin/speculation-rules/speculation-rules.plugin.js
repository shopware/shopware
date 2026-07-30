/*
 * @sw-package framework
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

        /**
         * Listing links carry query parameters (page, sorting, filters). They are prefetched instead of
         * prerendered: a pagination bar holds up to nine links, and prerendering each one would render
         * a full page per hovered link on the server.
         *
         * Set to `false` to disable. An empty array does not work, because the plugin option merge
         * concatenates arrays instead of replacing them.
         */
        selectorListingLinks: ['.pagination .page-link'],

        /**
         * Value of the `No-Vary-Search` header the target pages are served with, provided by the server.
         */
        expectsNoVarySearch: null,
    };

    init() {
        if (
            HTMLScriptElement.supports &&
            HTMLScriptElement.supports('speculationrules')
        ) {
            this._removeSpeculationRulesScriptTag();

            const prerenderRules = this.customizeRules(this._applyNoVarySearch([
                ...this._getProductSpeculationRules(),
                ...this._getTopNavigationSpeculationRules(),
            ]));

            const prefetchRules = this.customizePrefetchRules(this._applyNoVarySearch(
                this._getListingSpeculationRules(),
            ));

            this._addSpeculationRulesScriptTag(prerenderRules, prefetchRules);
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
     * Extension point for the `prefetch` rules, the counterpart of `customizeRules` for `prerender`.
     *
     * @param rules
     * @returns []
     */
    customizePrefetchRules(rules) {
        return rules;
    }

    /**
     * Declares the query string relaxation the target pages are served with, so a speculation started
     * for one URL can also satisfy a navigation to a URL that differs only in ignored ways. Without it
     * the browser has to start a second speculation, because it cannot know the match will be allowed
     * before the response arrives. The value must match the `No-Vary-Search` response header, which is
     * why it is provided by the server instead of being hardcoded here.
     *
     * @param rules
     * @returns []
     * @private
     */
    _applyNoVarySearch(rules) {
        if (!this.options.expectsNoVarySearch) {
            return rules;
        }

        return rules.map((rule) => ({
            ...rule,
            expects_no_vary_search: this.options.expectsNoVarySearch,
        }));
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
     * Listing links such as pagination carry query parameters, which is exactly where the parameter
     * order of a URL starts to diverge: the server renders `?p=2&search=x`, while the pagination plugin
     * derives `?search=x&p=2` from the current URL. `expects_no_vary_search` lets one speculation serve
     * both, instead of the browser starting a second one.
     *
     * @returns {[{source: string, where: {and: [{href_matches: string},{selector_matches: *}]}, eagerness: string}]}
     * @private
     */
    _getListingSpeculationRules() {
        const selectors = this.options.selectorListingLinks;

        if (!Array.isArray(selectors) || selectors.length === 0) {
            return [];
        }

        return [
            {
                source: 'document',
                where: {
                    and: [
                        { href_matches: `${window.location.origin}/*` },
                        { selector_matches: selectors },
                    ],
                },
                eagerness: 'moderate',
            },
        ];
    }

    /**
     * @param preRenderRules
     * @param preFetchRules
     * @private
     */
    _addSpeculationRulesScriptTag(preRenderRules, preFetchRules = []) {
        if (this.speculationRulesScriptTag) {
            document.head.removeChild(this.speculationRulesScriptTag);
        }

        const rules = { prerender: preRenderRules };
        if (preFetchRules.length) {
            rules.prefetch = preFetchRules;
        }

        this.speculationRulesScriptTag = document.createElement('script');
        this.speculationRulesScriptTag.type = 'speculationrules';
        this.speculationRulesScriptTag.innerHTML = JSON.stringify(rules);

        document.head.appendChild(this.speculationRulesScriptTag);
    }
}
