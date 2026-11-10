/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-vuex-state');
const data = require('./no-vuex-state.data');

const state = (shape) => data.stateMessage('Shopware.State', shape);
const todo = (text) => `// TODO Codemod(6.8, vuex-state): ${text}`;
const VUEX_IMPORT = data.imports[0].message;

describe('no-vuex-state data', () => {
    it('names the Pinia id of renamed Vuex modules', () => {
        expect(state({ kind: 'get', module: 'swFlowState', rest: '.flow' })).toBe(
            "Shopware.State (Vuex) is removed. Use Shopware.Store.get('swFlow').flow (Pinia id, the Vuex id was swFlowState).",
        );
        expect(data.renamedStores).toEqual({
            cmsPageState: 'cmsPage',
            swFlowState: 'swFlow',
            paymentOverviewCardState: 'paymentOverviewCard',
        });
    });

    it('keeps unchanged ids and names modules without a store', () => {
        expect(state({ kind: 'get', module: 'session' })).toBe("Shopware.State (Vuex) is removed. Use Shopware.Store.get('session').");
        expect(state({ kind: 'get', module: 'usageData' })).toContain('has no Pinia store');
    });

    it('describes every Vuex form with its Pinia counterpart', () => {
        expect(state({ kind: 'getter', module: 'cmsPageState', name: 'isSystemDefaultLanguage' })).toContain(
            "Shopware.Store.get('cmsPage').isSystemDefaultLanguage",
        );
        expect(state({ kind: 'commit', module: 'swOrder', name: 'setCart' })).toContain('Pinia has no mutations');
        expect(state({ kind: 'dispatch', module: 'swOrder', name: 'saveOrder' })).toContain("Shopware.Store.get('swOrder').saveOrder(…)");
        expect(state({ kind: 'registerModule', module: 'acme' })).toContain("Shopware.Store.register({ id: 'acme'");
        expect(state({ kind: 'unregisterModule', module: 'acme' })).toContain("Shopware.Store.unregister('acme')");
        expect(state({ kind: 'watch' })).toContain("Vue's watch(");
        expect(state({ kind: 'subscribe' })).toContain('$subscribe(callback)');
        expect(state({ kind: 'subscribeAction' })).toContain('$onAction(callback)');
        expect(state({ kind: 'list' })).toContain('Shopware.Store.list()');
        expect(data.mapHelperMessage('mapVuexGetters', 'swFlowState')).toContain("mapState(() => Shopware.Store.get('swFlow'), [ … ])");
        expect(data.mapHelperMessage('mapVuexMutations', null)).toContain('mapActions(() => Shopware.Store.get(id), [ … ])');
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-vuex-state (script)', rule, {
    valid: [
        { name: 'ignores Shopware.Store', code: "Shopware.Store.get('swFlow').flow;\nShopware.Store.register({ id: 'acme' });" },
        { name: 'ignores Pinia helpers', code: "const { mapState } = Shopware.Component.getComponentHelper();\nmapState(() => Shopware.Store.get('swFlow'), ['flow']);" },
        { name: 'ignores other State objects', code: "const State = {};\nState.get('x');\nthis.state.get('x');" },
        { name: 'ignores pinia imports', code: "import { defineStore } from 'pinia';" },
    ],
    invalid: [
        {
            name: 'marks Shopware.State by form, with the Pinia id',
            code: [
                "const flow = Shopware.State.get('swFlowState').flow;",
                "Shopware.State.getters['cmsPageState/isSystemDefaultLanguage'];",
                "Shopware.State.commit('swOrder/setCart', cart);",
                "Shopware.State.dispatch('swOrder/saveOrder', order);",
                "Shopware.State.registerModule('acmeStore', definition);",
                "Shopware.State.unregisterModule('acmeStore');",
            ].join('\n'),
            output: [
                todo(state({ kind: 'get', module: 'swFlowState', rest: '.flow' })),
                "const flow = Shopware.State.get('swFlowState').flow;",
                todo(state({ kind: 'getter', module: 'cmsPageState', name: 'isSystemDefaultLanguage' })),
                "Shopware.State.getters['cmsPageState/isSystemDefaultLanguage'];",
                todo(state({ kind: 'commit', module: 'swOrder', name: 'setCart' })),
                "Shopware.State.commit('swOrder/setCart', cart);",
                todo(state({ kind: 'dispatch', module: 'swOrder', name: 'saveOrder' })),
                "Shopware.State.dispatch('swOrder/saveOrder', order);",
                todo(state({ kind: 'registerModule', module: 'acmeStore' })),
                "Shopware.State.registerModule('acmeStore', definition);",
                todo(state({ kind: 'unregisterModule', module: 'acmeStore' })),
                "Shopware.State.unregisterModule('acmeStore');",
            ].join('\n'),
            errors: [
                { message: state({ kind: 'get', module: 'swFlowState', rest: '.flow' }) },
                { message: state({ kind: 'getter', module: 'cmsPageState', name: 'isSystemDefaultLanguage' }) },
                { message: state({ kind: 'commit', module: 'swOrder', name: 'setCart' }) },
                { message: state({ kind: 'dispatch', module: 'swOrder', name: 'saveOrder' }) },
                { message: state({ kind: 'registerModule', module: 'acmeStore' }) },
                { message: state({ kind: 'unregisterModule', module: 'acmeStore' }) },
            ],
        },
        {
            name: 'marks const { State } = Shopware, its uses and the subscriptions',
            code: [
                'const { State, Mixin } = Shopware;',
                "State.watch((s) => s.session, callback);",
                'State.subscribe(callback);',
                'State.subscribeAction(callback);',
                'State.list();',
                'State._store;',
            ].join('\n'),
            output: [
                todo(state({ kind: 'other' })),
                'const { State, Mixin } = Shopware;',
                todo(state({ kind: 'watch' })),
                "State.watch((s) => s.session, callback);",
                todo(state({ kind: 'subscribe' })),
                'State.subscribe(callback);',
                todo(state({ kind: 'subscribeAction' })),
                'State.subscribeAction(callback);',
                todo(state({ kind: 'list' })),
                'State.list();',
                todo(state({ kind: 'other' })),
                'State._store;',
            ].join('\n'),
            errors: [
                { message: state({ kind: 'other' }) },
                { message: state({ kind: 'watch' }) },
                { message: state({ kind: 'subscribe' }) },
                { message: state({ kind: 'subscribeAction' }) },
                { message: state({ kind: 'list' }) },
                { message: state({ kind: 'other' }) },
            ],
        },
        {
            name: 'marks this.$store, the factory container and aliases',
            code: [
                "this.$store.state.paymentOverviewCardState.cards;",
                "this.$store.dispatch('swFlowState/fetchTriggerActions');",
                "const factoryState = Shopware.Application.getContainer('factory').state;",
                'const store = Shopware.State;',
            ].join('\n'),
            output: [
                todo(data.stateMessage('this.$store', { kind: 'get', module: 'paymentOverviewCardState', rest: '.cards' })),
                "this.$store.state.paymentOverviewCardState.cards;",
                todo(data.stateMessage('this.$store', { kind: 'dispatch', module: 'swFlowState', name: 'fetchTriggerActions' })),
                "this.$store.dispatch('swFlowState/fetchTriggerActions');",
                todo(data.stateMessage("Shopware.Application.getContainer('factory').state", { kind: 'other' })),
                "const factoryState = Shopware.Application.getContainer('factory').state;",
                todo(state({ kind: 'other' })),
                'const store = Shopware.State;',
            ].join('\n'),
            errors: [
                { message: data.stateMessage('this.$store', { kind: 'get', module: 'paymentOverviewCardState', rest: '.cards' }) },
                { message: data.stateMessage('this.$store', { kind: 'dispatch', module: 'swFlowState', name: 'fetchTriggerActions' }) },
                { message: data.stateMessage("Shopware.Application.getContainer('factory').state", { kind: 'other' }) },
                { message: state({ kind: 'other' }) },
            ],
        },
        {
            name: 'marks the mapVuex helpers and vuex imports',
            code: [
                "import { mapGetters } from 'vuex';",
                'const { mapVuexState } = Shopware.Component.getComponentHelper();',
                'export default {',
                '    computed: {',
                "        ...mapVuexState('swFlowState', ['flow']),",
                "        ...Shopware.Component.getComponentHelper().mapVuexGetters('swOrder', ['isLoading']),",
                '    },',
                '};',
            ].join('\n'),
            output: [
                todo(VUEX_IMPORT),
                "import { mapGetters } from 'vuex';",
                todo(data.mapHelperMessage('mapVuexState', null)),
                'const { mapVuexState } = Shopware.Component.getComponentHelper();',
                'export default {',
                '    computed: {',
                `        ${todo(data.mapHelperMessage('mapVuexState', 'swFlowState'))}`,
                "        ...mapVuexState('swFlowState', ['flow']),",
                `        ${todo(data.mapHelperMessage('mapVuexGetters', 'swOrder'))}`,
                "        ...Shopware.Component.getComponentHelper().mapVuexGetters('swOrder', ['isLoading']),",
                '    },',
                '};',
            ].join('\n'),
            errors: [
                { message: VUEX_IMPORT },
                { message: data.mapHelperMessage('mapVuexState', null) },
                { message: data.mapHelperMessage('mapVuexState', 'swFlowState') },
                { message: data.mapHelperMessage('mapVuexGetters', 'swOrder') },
            ],
        },
        {
            name: 'reports a marked access without a fix',
            code: [todo(state({ kind: 'list' })), 'Shopware.State.list();'].join('\n'),
            output: null,
            errors: [{ message: state({ kind: 'list' }) }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('@typescript-eslint/parser'), ecmaVersion: 2022, sourceType: 'module' } }).run(
    'no-vuex-state (TypeScript)',
    rule,
    {
        valid: [{ name: 'ignores PiniaRootState', code: 'declare global {\n    interface PiniaRootState {\n        acme: AcmeStore;\n    }\n}' }],
        invalid: [
            {
                name: 'marks VuexRootState augmentations',
                code: 'declare global {\n    interface VuexRootState {\n        acme: AcmeState;\n    }\n}',
                output: `declare global {\n    ${todo(data.rootStateMessage)}\n    interface VuexRootState {\n        acme: AcmeState;\n    }\n}`,
                errors: [{ message: data.rootStateMessage }],
            },
        ],
    },
);

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-vuex-state (template)', rule, {
    valid: [
        {
            name: 'ignores other identifiers and a store property',
            filename: 'acme-card.html.twig',
            code: '<template>\n    <div>{{ store.$store }} {{ flow }}</div>\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks $store in Twig templates, in directives and in Twig output',
            filename: 'acme-card.html.twig',
            code: [
                '<template>',
                '    <div',
                '        :class="{ busy: $store.state.swFlowState.isLoading }"',
                '    >',
                "        {{ $store.getters['swOrder/currency'] }}",
                '    </div>',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                `    {# TODO Codemod(6.8, vuex-state): ${data.stateMessage('$store', { kind: 'get', module: 'swFlowState', rest: '.isLoading' })} #}`,
                '    <div',
                '        :class="{ busy: $store.state.swFlowState.isLoading }"',
                '    >',
                `        {# TODO Codemod(6.8, vuex-state): ${data.stateMessage('$store', { kind: 'getter', module: 'swOrder', name: 'currency' })} #}`,
                "        {{ $store.getters['swOrder/currency'] }}",
                '    </div>',
                '</template>',
            ].join('\n'),
            errors: [
                { message: data.stateMessage('$store', { kind: 'get', module: 'swFlowState', rest: '.isLoading' }) },
                { message: data.stateMessage('$store', { kind: 'getter', module: 'swOrder', name: 'currency' }) },
            ],
        },
        {
            name: 'marks $store.state in Twig output',
            filename: 'acme-card.html.twig',
            code: '<template>\n    <p>{{ $store.state.cmsPageState.currentPage.name }}</p>\n</template>',
            output: [
                '<template>',
                `    {# TODO Codemod(6.8, vuex-state): ${data.stateMessage('$store', { kind: 'get', module: 'cmsPageState', rest: '.currentPage' })} #}`,
                '    <p>{{ $store.state.cmsPageState.currentPage.name }}</p>',
                '</template>',
            ].join('\n'),
            errors: [{ message: data.stateMessage('$store', { kind: 'get', module: 'cmsPageState', rest: '.currentPage' }) }],
        },
        {
            name: 'marks $store in .vue templates and this.$store in their script',
            filename: 'acme-card.vue',
            code: [
                '<template>',
                '    <span>{{ $store }}</span>',
                '</template>',
                '<script>',
                "export default { created() { this.$store.commit('acme/set', 1); } };",
                '</script>',
            ].join('\n'),
            output: [
                '<template>',
                `    <!-- TODO Codemod(6.8, vuex-state): ${data.stateMessage('$store', { kind: 'other' })} -->`,
                '    <span>{{ $store }}</span>',
                '</template>',
                '<script>',
                todo(data.stateMessage('this.$store', { kind: 'commit', module: 'acme', name: 'set' })),
                "export default { created() { this.$store.commit('acme/set', 1); } };",
                '</script>',
            ].join('\n'),
            errors: [
                { message: data.stateMessage('$store', { kind: 'other' }) },
                { message: data.stateMessage('this.$store', { kind: 'commit', module: 'acme', name: 'set' }) },
            ],
        },
    ],
});
