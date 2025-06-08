import SpeculationRulesPlugin from 'src/plugin/speculation-rules/speculation-rules.plugin';

describe('SpeculationRulesPlugin', () => {
    let speculationRulesPlugin;

    beforeEach(() => {
        jest.clearAllMocks();

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
        expect(speculationRulesPlugin._addSpeculationRulesScriptTag).toHaveBeenCalledWith(expect.any(Array));
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
});
