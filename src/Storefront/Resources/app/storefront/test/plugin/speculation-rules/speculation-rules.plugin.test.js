import SpeculationRulesPlugin from 'src/plugin/speculation-rules/speculation-rules.plugin';

describe('SpeculationRulesPlugin', () => {
    let speculationRulesPlugin;

    beforeEach(() => {
        jest.clearAllMocks();

        // the plugin appends its script tag to the head, so it has to be reset between tests
        document.head.innerHTML = '';

        document.body.innerHTML = `
            <div class="header-logo-main-link"></div>
            <div class="main-navigation-link"></div>
            <div class="nav-link is-level-0"></div>
            <div class="nav-link is-level-1"></div>
            <div class="product-image-link"></div>
            <div class="product-name"></div>
            <div class="btn-detail"></div>
        `;

        speculationRulesPlugin = new SpeculationRulesPlugin(document.querySelector('.header-logo-main-link'));
    });

    test('should initialize and add speculation rules script tag', () => {
        HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
        speculationRulesPlugin._removeSpeculationRulesScriptTag = jest.fn();
        speculationRulesPlugin._addSpeculationRulesScriptTag = jest.fn();

        speculationRulesPlugin.init();

        expect(speculationRulesPlugin._removeSpeculationRulesScriptTag).toHaveBeenCalled();
        expect(speculationRulesPlugin._addSpeculationRulesScriptTag).toHaveBeenCalledWith(expect.any(Array), expect.any(Array));
    });

    test('should remove existing speculation rules script tag', () => {
        const scriptTag = document.createElement('script');
        document.head.appendChild(scriptTag);
        speculationRulesPlugin.speculationRulesScriptTag = scriptTag;

        speculationRulesPlugin._removeSpeculationRulesScriptTag();

        expect(document.head.contains(scriptTag)).toBe(false);
    });

    test('should add speculation rules script tag with correct rules', () => {
        speculationRulesPlugin.speculationRulesScriptTag = null;
        const rules = [
            ...speculationRulesPlugin._getProductSpeculationRules(),
            ...speculationRulesPlugin._getTopNavigationSpeculationRules(),
        ];

        speculationRulesPlugin._addSpeculationRulesScriptTag(rules);

        const scriptTag = document.head.querySelector('script[type="speculationrules"]');
        expect(scriptTag).not.toBeNull();
        expect(scriptTag.innerHTML).toBe(JSON.stringify({ prerender: rules }));
    });

    test('should remove existing speculation rules script tag before adding a new one', () => {
        const oldScriptTag = document.createElement('script');
        oldScriptTag.type = 'speculationrules';
        document.head.appendChild(oldScriptTag);
        speculationRulesPlugin.speculationRulesScriptTag = oldScriptTag;

        const newRules = [
            ...speculationRulesPlugin._getProductSpeculationRules(),
            ...speculationRulesPlugin._getTopNavigationSpeculationRules(),
        ];

        speculationRulesPlugin._addSpeculationRulesScriptTag(newRules);

        const newScriptTag = document.head.querySelector('script[type="speculationrules"]');
        expect(newScriptTag).not.toBeNull();
        expect(newScriptTag).not.toBe(oldScriptTag);
        expect(document.head.contains(oldScriptTag)).toBe(false);
    });

    describe('listing links', () => {
        const emitted = () => JSON.parse(document.head.querySelector('script[type="speculationrules"]').innerHTML);

        test('should be prefetched, not prerendered', () => {
            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
            new SpeculationRulesPlugin(document.querySelector('.header-logo-main-link'));

            const rules = emitted();

            expect(rules.prefetch).toHaveLength(1);
            expect(rules.prefetch[0].where.and[1].selector_matches).toEqual(['.pagination .page-link']);
            expect(rules.prefetch[0].eagerness).toBe('moderate');

            // product and navigation links stay in the prerender bucket
            expect(rules.prerender).toHaveLength(2);
            const prerenderSelectors = rules.prerender.flatMap((r) => r.where.and[1].selector_matches);
            expect(prerenderSelectors).not.toContain('.pagination .page-link');
        });

        test('should omit the prefetch bucket when disabled with false', () => {
            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
            new SpeculationRulesPlugin(document.querySelector('.header-logo-main-link'), { selectorListingLinks: false });

            const rules = emitted();

            expect(rules).not.toHaveProperty('prefetch');
            expect(rules.prerender).toHaveLength(2);
        });

        test('should carry expects_no_vary_search as well', () => {
            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
            new SpeculationRulesPlugin(document.querySelector('.header-logo-main-link'), { expectsNoVarySearch: 'key-order' });

            expect(emitted().prefetch[0].expects_no_vary_search).toBe('key-order');
        });

        test('should stay customizable through customizePrefetchRules', () => {
            class CustomizedPlugin extends SpeculationRulesPlugin {
                customizePrefetchRules(rules) {
                    return rules.map((rule) => ({ ...rule, eagerness: 'conservative' }));
                }
            }

            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
            new CustomizedPlugin(document.querySelector('.header-logo-main-link'));

            const rules = emitted();
            expect(rules.prefetch[0].eagerness).toBe('conservative');
            // prerender rules are untouched by the prefetch extension point
            expect(rules.prerender[0].eagerness).toBe('moderate');
        });
    });

    describe('expects_no_vary_search', () => {
        /**
         * The plugin renders its script tag during construction, so options have to be passed in
         * instead of being mutated afterwards.
         */
        const createPlugin = (options = {}) => {
            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);

            return new SpeculationRulesPlugin(document.querySelector('.header-logo-main-link'), options);
        };

        const emittedRules = () => {
            const scriptTag = document.head.querySelector('script[type="speculationrules"]');
            expect(scriptTag).not.toBeNull();

            return JSON.parse(scriptTag.innerHTML).prerender;
        };

        test('should not be set when no value is provided', () => {
            createPlugin();

            const rules = emittedRules();
            expect(rules).toHaveLength(2);
            rules.forEach((rule) => {
                expect(rule).not.toHaveProperty('expects_no_vary_search');
            });
        });

        test('should not be set when the value is null', () => {
            createPlugin({ expectsNoVarySearch: null });

            emittedRules().forEach((rule) => {
                expect(rule).not.toHaveProperty('expects_no_vary_search');
            });
        });

        test('should be set on every rule when a value is provided', () => {
            createPlugin({ expectsNoVarySearch: 'key-order, params=("utm_source")' });

            const rules = emittedRules();
            expect(rules).toHaveLength(2);
            rules.forEach((rule) => {
                expect(rule.expects_no_vary_search).toBe('key-order, params=("utm_source")');
                // the original rule stays intact
                expect(rule.source).toBe('document');
                expect(rule.eagerness).toBe('moderate');
                expect(rule.where).toBeDefined();
            });
        });

        test('should stay customizable through customizeRules', () => {
            class CustomizedPlugin extends SpeculationRulesPlugin {
                customizeRules(rules) {
                    return rules.map((rule) => ({ ...rule, expects_no_vary_search: 'params' }));
                }
            }

            HTMLScriptElement.supports = jest.fn().mockReturnValue(true);
            new CustomizedPlugin(document.querySelector('.header-logo-main-link'), { expectsNoVarySearch: 'key-order' });

            emittedRules().forEach((rule) => {
                expect(rule.expects_no_vary_search).toBe('params');
            });
        });
    });
});
