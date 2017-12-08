import { sync } from 'vuex-router-sync';
import VueX, {
    mapState,
    mapGetters,
    mapActions,
    mapMutations
} from 'vuex';
import utils from 'src/core/service/util.service';

/**
 * Core store definition
 * @type {Object}
 */
import storeDefinition from 'src/core/store';

/**
 * Registry for the state tree namespaces
 * @type {Map}
 */
const namespaceRegistry = new Map();

/**
 * VueX store instance
 * @type {Store}
 */
let storeInstance;
let unsync;

export default {
    mapRouterToState,
    unmapRouterFromState,
    getStoreInstance,
    mapState,
    mapGetters,
    mapActions,
    mapMutations,
    registerStateModule,
    removeStateModule,
    install
};

/**
 * Maps the router changes to the global state object.
 *
 * @param {VueRouter} router
 * @returns {Function}
 */
function mapRouterToState(router) {
    unsync = sync(storeInstance, router);

    return unsync;
}

/**
 * Removes the listeners on the router to detect route changes. After calling this method route changes are not
 * reflected in the state object anymore.
 *
 * @returns {boolean}
 */
function unmapRouterFromState() {
    if (!unsync) {
        return false;
    }

    unsync();
    return true;
}

/**
 * Creates a store instance with the necessary configuration & plugins.
 *
 * @param {Object} definition Store definition, see <https://vuex.vuejs.org/en/state.html>
 * @returns {Store} instanced VueX store
 */
function createStoreInstance(definition) {
    return new VueX.Store(definition);
}

/**
 * Returns the {@link VueX.Store} instance. If the instance wasn't created before, we're creating a new instance with
 * the provided {@link storeDefinition}.
 *
 * Modules don't need to modify the core store definition to insert their state into the state tree. Please head over
 * to the {@link #registerStateModule} method.
 *
 * @returns {Store}
 */
function getStoreInstance() {
    if (!storeInstance) {
        storeInstance = createStoreInstance(storeDefinition);
    }
    return storeInstance;
}

/**
 * Registers a module dynamically into the state tree. The module acts like an independent state tree. If not
 * explicitly defined any module will be namespaced.
 *
 * For the full documentation, see {@link https://vuex.vuejs.org/en/modules.html}
 *
 * @param {String|Array} namespacePath - module namespace path, usually the module name
 * @param {Object} moduleDefinition - module state definition
 * @returns {Boolean}
 */
function registerStateModule(namespacePath, moduleDefinition) {
    const namespaceAsString = (utils.isArray(namespacePath) ? namespacePath.join('/') : namespacePath);

    // Namespaced module
    if (!moduleDefinition.namespaced) {
        moduleDefinition.namespaced = true;
    }

    // The state property should be a function, so we can reuse the module under a different namespace
    if (!utils.isFunction(moduleDefinition.state)) {
        utils.warn(
            'StateFactory',
            'The "state" property should be a function and returning an object with the initial state.',
        );

        return false;
    }

    moduleDefinition = generateMutationsForStateProperties(moduleDefinition);
    storeInstance.registerModule(namespacePath, moduleDefinition);
    namespaceRegistry.set(namespaceAsString, moduleDefinition);

    return true;
}

/**
 * Generates mutations for the initial state properties for easier usage of the state tree. The generated mutations are
 * prefixed with "$update" to avoid conflicts with given mutations from the provided state definition.
 *
 * @param {Object} definition - State definition
 * @returns {Object} Definition with generated mutations
 */
function generateMutationsForStateProperties(definition) {
    const initialState = definition.state();
    const generatedMutations = {};

    Object.keys(initialState).forEach((key) => {
        const propKey = getMutationName(key);
        generatedMutations[propKey] = function generatedMutation(state, payload) {
            state[key] = payload;
        };
    });

    definition.mutations = { ...definition.mutations, ...generatedMutations };
    return definition;
}

/**
 * Removes a dynamically registered module from the global state tree
 *
 * @param {String|Array} namespacePath
 * @returns {Boolean}
 */
function removeStateModule(namespacePath) {
    storeInstance.unregisterModule(namespacePath);

    return true;
}

/**
 * Returns an unique name for the mutation name. The name is prefixed to avoid conflicts with mutations defined in the state
 * tree definition.
 *
 * @param {String} name Name which should be used for the mutation name
 * @param {String} [prefix=_swUpdate] Prefix to use for the mutation name
 * @returns {string} Mutation name
 */
function getMutationName(name, prefix = '_swUpdate') {
    return `${prefix}${utils.capitalizeString(name)}`;
}

/**
 * Helper method which installs the plugin to our view layer instance and adds a mixin
 * to the view layer which provides the state tree to each component.
 *
 * Please make sure the {@link #storeInstance} was created before calling this method.
 *
 * @returns {Boolean}
 */
function install(Vue) {
    Vue.mixin({
        beforeCreate() {
            this.$store = storeInstance;

            if (!this.$options.stateMapping) {
                return;
            }
            const stateMapping = this.$options.stateMapping;
            const stateKey = stateMapping.state;

            if (!namespaceRegistry.has(stateKey)) {
                utils.warn(
                    'StateFactory',
                    `"${stateKey}" is not registered in the state tree, the state can't be mapped.`,
                    `The following keys are valid: ${Array.from(namespaceRegistry.keys()).join(', ')}`
                );
                return;
            }

            const stateDefinition = namespaceRegistry.get(stateKey);
            const initialState = stateDefinition.state();

            // Generate computed properties, either the defined properties or all properties from the state, if not false
            // or empty array provided
            if (!Object.prototype.hasOwnProperty.bind(stateMapping, 'properties')
                || stateMapping.properties !== false
                || (utils.isArray(stateMapping.properties) && !stateMapping.properties.length)
            ) {
                const generatedComputed = {};
                let propertyMapping = Object.keys(initialState);

                if (utils.isArray(stateMapping.properties)) {
                    propertyMapping = stateMapping.properties;
                }

                Object.keys(initialState).forEach((key) => {
                    // Just generate computed properties for the user defined properties
                    if (propertyMapping.indexOf(key) === -1) {
                        return;
                    }

                    // Support for "v-model", see {@link https://vuex.vuejs.org/en/forms.html#two-way-computed-property}
                    generatedComputed[key] = {
                        get() {
                            return this.$store.state[stateKey][key];
                        },
                        set(value) {
                            this.$store.commit(
                                `${stateKey}/${getMutationName(key)}`,
                                value
                            );
                        }
                    };
                });

                this.$options.computed = {
                    ...this.$options.computed,
                    ...generatedComputed
                };
            }

            // Don't try to map actions when no actions are defined
            if (!stateDefinition.actions || stateDefinition.actions.length < 1) {
                return;
            }

            // Either map the defined actions or all available actions from the state definition
            const actions = stateMapping.actions || Object.keys(stateDefinition.actions);

            // If the user provides `false` or an empty array, we don't map the actions
            if (stateMapping.actions === false || (utils.isArray(stateMapping.actions) && stateMapping.actions.length)) {
                return;
            }
            this.$options.methods = {
                ...this.$options.methods,
                ...mapActions(stateKey, actions)
            };
        }
    });

    Vue.use(VueX);

    return true;
}
