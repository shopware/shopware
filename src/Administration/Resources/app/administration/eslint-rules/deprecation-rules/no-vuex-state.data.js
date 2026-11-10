/**
 * @sw-package framework
 *
 * Texts of the `vuex-state` codemod. 6.8 removes Vuex from the Administration: `Shopware.State`, the `state` factory
 * of the container, `$store`, the `mapVuex*` component helpers, the `vuex` package and `VuexRootState`. Taken from
 * the `@deprecated tag:v6.8.0` markers in `src/core/shopware.ts`, `src/core/factory/state.factory.ts` and
 * `src/global.types.ts`.
 *
 * The core moved all its Vuex modules to Pinia stores in 6.6 and 6.7. Three of them changed their id on the way.
 */

/** Vuex module id → Pinia store id, for the modules whose id changed. */
const renamedStores = {
    cmsPageState: 'cmsPage',
    swFlowState: 'swFlow',
    paymentOverviewCardState: 'paymentOverviewCard',
};

/** Vuex modules of the core without a Pinia store. */
const removedStores = ['usageData'];

const ANY_STORE = 'Shopware.Store.get(id)';

/**
 * `Shopware.Store.get('<pinia id>')` for a Vuex module id, and the note on a renamed id.
 *
 * @param {string|null} module
 * @returns {{ store: string, note: string }}
 */
function piniaStore(module) {
    if (!module) {
        return { store: ANY_STORE, note: '' };
    }

    const id = Object.hasOwn(renamedStores, module) ? renamedStores[module] : module;

    return {
        store: `Shopware.Store.get('${id}')`,
        note: id === module ? '' : ` (Pinia id, the Vuex id was ${module})`,
    };
}

/**
 * The text for a Vuex API, by the form the rule found.
 *
 * @param {string} label how the code reaches Vuex, e.g. `Shopware.State` or `this.$store`
 * @param {object} shape
 * @param {string} shape.kind `get`, `getter`, `commit`, `dispatch`, `registerModule`, `unregisterModule`, `watch`,
 *     `subscribe`, `subscribeAction`, `list` or `other`
 * @param {string|null} [shape.module] the Vuex module id, when it is a literal
 * @param {string|null} [shape.name] the getter, mutation or action
 * @param {string} [shape.rest] the property read from the store, e.g. `.flow`
 * @returns {string}
 */
function stateMessage(label, { kind, module = null, name = null, rest = '' }) {
    const { store, note } = piniaStore(module);
    let advice;

    switch (kind) {
        case 'get':
            advice = removedStores.includes(module)
                ? `The Vuex module ${module} has no Pinia store; remove this access.`
                : `Use ${store}${rest}${note}.`;
            break;
        case 'getter':
            advice = `Pinia getters are store properties: use ${store}.${name ?? '<getter>'}${note}.`;
            break;
        case 'commit':
            advice = `Pinia has no mutations: call an action or assign the state on ${store}${note}.`;
            break;
        case 'dispatch':
            advice = `Call the action on the Pinia store: ${store}.${name ?? '<action>'}(…)${note}.`;
            break;
        case 'registerModule':
            advice =
                `Register a Pinia store: Shopware.Store.register({ id: ${module ? `'${module}'` : 'id'}, state: () => ({ … }), ` +
                'getters, actions }); mutations become actions.';
            break;
        case 'unregisterModule':
            advice = `Use Shopware.Store.unregister(${module ? `'${module}'` : 'id'}).`;
            break;
        case 'watch':
            advice = `Use Vue's watch(() => ${ANY_STORE}.<property>, callback).`;
            break;
        case 'subscribe':
            advice = `Use ${ANY_STORE}.$subscribe(callback).`;
            break;
        case 'subscribeAction':
            advice = `Use ${ANY_STORE}.$onAction(callback).`;
            break;
        case 'list':
            advice = 'Use Shopware.Store.list().';
            break;
        default:
            advice = `Use the Pinia stores: ${ANY_STORE}.`;
    }

    return `${label} (Vuex) is removed. ${advice}`;
}

/** Vuex component helper → the Pinia helper of `Shopware.Component.getComponentHelper()` that replaces it. */
const mapHelpers = {
    mapVuexState: 'mapState',
    mapVuexGetters: 'mapState',
    mapVuexMutations: 'mapActions',
    mapVuexActions: 'mapActions',
};

/**
 * @param {string} helper e.g. `mapVuexState`
 * @param {string|null} module the namespace argument, when it is a literal
 * @returns {string}
 */
function mapHelperMessage(helper, module) {
    const { store, note } = piniaStore(module);

    return (
        `${helper}() is removed with Vuex. Use ${mapHelpers[helper]}(() => ${store}, [ … ])${note} ` +
        'from Shopware.Component.getComponentHelper().'
    );
}

module.exports = {
    id: 'vuex-state',
    description: 'Marks Vuex APIs, which 6.8 removes in favor of Pinia stores in Shopware.Store',
    renamedStores,
    removedStores,
    stateMessage,
    mapHelpers,
    mapHelperMessage,
    rootStateMessage: 'VuexRootState is removed with Vuex. Declare the type of the store in PiniaRootState.',
    imports: [
        {
            source: /^vuex(\/|$)/,
            message:
                'vuex is removed from the Administration in 6.8. Use Pinia stores: Shopware.Store.register() and Shopware.Store.get().',
        },
    ],
};
