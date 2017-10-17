webpackJsonp([2],{

/***/ 117:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__ = __webpack_require__(28);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(20);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__ = __webpack_require__(114);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_twig__ = __webpack_require__(332);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_twig__);





/* harmony default export */ __webpack_exports__["default"] = ({
    registerComponentTemplate: registerComponentTemplate,
    extendComponentTemplate: extendComponentTemplate,
    registerTemplateOverride: registerTemplateOverride,
    getRenderedTemplate: getRenderedTemplate,
    getTemplateOverrides: getTemplateOverrides,
    getTemplateRegistry: getTemplateRegistry,
    findCustomTemplate: findCustomTemplate,
    findCustomOverride: findCustomOverride
});

var templateRegistry = new __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map___default.a();

__WEBPACK_IMPORTED_MODULE_3_twig___default.a.extend(function (TwigCore) {
    TwigCore.token.definitions = [TwigCore.token.definitions[0], TwigCore.token.definitions[1], TwigCore.token.definitions[5], TwigCore.token.definitions[6], TwigCore.token.definitions[7], TwigCore.token.definitions[9], TwigCore.token.definitions[10]];

    TwigCore.exports.extendTag({
        type: 'parent',
        regex: /^parent/,
        next: [],
        open: true,

        parse: function parse(token, context, chain) {
            return {
                chain: chain,
                output: TwigCore.placeholders.parent
            };
        }
    });

    TwigCore.exports.placeholders = TwigCore.placeholders;
});

function registerComponentTemplate(componentName) {
    var componentTemplate = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;

    var template = templateRegistry.get(componentName) || {};

    if (componentTemplate === null) {
        componentTemplate = findCustomTemplate(componentName);
    }

    var templateConfig = {
        id: componentName + '-baseTemplate',
        data: componentTemplate
    };

    template.baseTemplate = __WEBPACK_IMPORTED_MODULE_3_twig___default.a.twig(templateConfig);

    templateRegistry.set(componentName, template);
}

function extendComponentTemplate(componentName, extendComponentName) {
    var templateExtension = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

    if (!templateRegistry.has(extendComponentName)) {
        if (templateExtension !== null) {
            registerComponentTemplate(componentName, templateExtension);
        }

        return;
    }

    var extendTemplate = templateRegistry.get(extendComponentName);
    var template = templateRegistry.get(componentName) || {};

    var templateConfig = {
        id: componentName + '-baseTemplate',
        data: extendTemplate.baseTemplate.tokens
    };

    template.baseTemplate = __WEBPACK_IMPORTED_MODULE_3_twig___default.a.twig(templateConfig);

    templateRegistry.set(componentName, template);

    if (templateExtension !== null) {
        registerTemplateOverride(componentName, templateExtension);
    }
}

function registerTemplateOverride(componentName) {
    var templateOverride = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
    var overrideIndex = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

    var template = templateRegistry.get(componentName) || {};

    template.overrides = template.overrides || [];

    if (templateOverride === null) {
        templateOverride = findCustomOverride(componentName);
    }

    var templateConfig = {
        id: componentName + '-' + template.overrides.length,
        data: templateOverride
    };

    var override = __WEBPACK_IMPORTED_MODULE_3_twig___default.a.twig(templateConfig);

    if (overrideIndex !== null) {
        template.overrides.splice(overrideIndex, 0, override);
    } else {
        template.overrides.push(override);
    }

    templateRegistry.set(componentName, template);
}

function getRenderedTemplate(componentName) {
    if (!templateRegistry.has(componentName)) {
        return '';
    }

    var template = templateRegistry.get(componentName);

    if (!template.baseTemplate) {
        return '';
    }

    var baseTemplate = template.baseTemplate;
    var overrides = template.overrides;
    var parentPlaceholder = __WEBPACK_IMPORTED_MODULE_3_twig___default.a.placeholders.parent;
    var blocks = {};

    baseTemplate.render();

    if (overrides) {
        overrides.forEach(function (override) {
            var templateBlocks = override.render({}, {
                output: 'blocks'
            });

            __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default()(blocks).forEach(function (blockName) {
                if (templateBlocks[blockName]) {
                    templateBlocks[blockName] = templateBlocks[blockName].replace(parentPlaceholder, blocks[blockName]);
                }
            });

            __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default()(blocks, templateBlocks);
        });
    }

    return baseTemplate.render({}, {
        blocks: blocks
    });
}

function getTemplateOverrides(componentName) {
    if (!templateRegistry.has(componentName)) {
        return [];
    }

    var template = templateRegistry.get(componentName);

    return template.overrides || [];
}

function getTemplateRegistry() {
    return templateRegistry;
}

function findCustomTemplate(componentName) {
    var element = document.querySelector('template[component="' + componentName + '"]');

    return element !== null ? element.innerHTML : '';
}

function findCustomOverride(componentName) {
    var element = document.querySelector('template[override="' + componentName + '"]');

    return element !== null ? element.innerHTML : '';
}

/***/ }),

/***/ 118:
/***/ (function(module, exports) {

/* (ignored) */

/***/ }),

/***/ 12:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(35);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__ = __webpack_require__(53);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof__ = __webpack_require__(108);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from__ = __webpack_require__(67);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty__ = __webpack_require__(85);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign__ = __webpack_require__(28);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys__ = __webpack_require__(20);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_uuid_v4__ = __webpack_require__(113);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_uuid_v4___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_7_uuid_v4__);









/* harmony default export */ __webpack_exports__["default"] = ({
    merge: merge,
    formDataToObject: formDataToObject,
    warn: warn,
    currency: currency,
    date: date,
    getObjectChangeSet: getObjectChangeSet,
    createUuid: __WEBPACK_IMPORTED_MODULE_7_uuid_v4___default.a,
    isObject: isObject,
    isEmpty: isEmpty,
    isArray: isArray,
    isFunction: isFunction,
    isDate: isDate
});

function merge(target, source) {
    __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys___default()(source).forEach(function (key) {
        if (source[key] instanceof Object) {
            if (!target[key]) {
                __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign___default()(target, __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty___default()({}, key, {}));
            }
            __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign___default()(source[key], merge(target[key], source[key]));
        }
    });

    __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign___default()(target || {}, source);
    return target;
}

function formDataToObject(formData) {
    return __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from___default()(formData).reduce(function (result, item) {
        result[item[0]] = item[1];
        return result;
    }, {});
}

function warn() {
    var name = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'Core';

    if (false) {
        for (var _len = arguments.length, message = Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
            message[_key - 1] = arguments[_key];
        }

        message.unshift('[' + name + ']');
        console.warn.apply(this, message);
    }
}

function currency(val, sign) {
    var opts = {
        style: 'currency',
        currency: sign || 'EUR'
    };
    var language = 'de-DE';
    if (opts.currency === 'USD') {
        language = 'en-US';
    }
    return val.toLocaleString(language, opts);
}

function date(val) {
    return val.toLocaleString('de-DE');
}

function isObject(object) {
    return object !== null && (typeof object === 'undefined' ? 'undefined' : __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof___default()(object)) === 'object';
}

function isEmpty(object) {
    return __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys___default()(object).length === 0;
}

function isArray(array) {
    return Array.isArray(array);
}

function isFunction(func) {
    return func !== null && typeof func === 'function';
}

function isDate(dateObject) {
    return dateObject instanceof Date;
}

function getObjectChangeSet(baseObject, compareObject) {
    if (baseObject === compareObject) {
        return {};
    }

    if (!isObject(baseObject) || !isObject(compareObject)) {
        return compareObject;
    }

    if (isDate(baseObject) || isDate(compareObject)) {
        if (baseObject.valueOf() === compareObject.valueOf()) {
            return {};
        }

        return compareObject;
    }

    var b = __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, baseObject);
    var c = __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, compareObject);

    return __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys___default()(c).reduce(function (acc, key) {
        if (b.hasOwnProperty(key)) {
            if (isArray(b[key])) {
                var arrayDiff = getArrayChangeSet(b[key], c[key]);

                if (isArray(arrayDiff) && arrayDiff.length === 0) {
                    return acc;
                }

                return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, acc, __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty___default()({}, key, arrayDiff));
            }

            var diff = getObjectChangeSet(b[key], c[key]);

            if (isObject(diff) && isEmpty(diff) && !isDate(diff)) {
                return acc;
            }

            if (isObject(b[key]) && b[key].uuid) {
                diff.uuid = b[key].uuid;
            }

            return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, acc, __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty___default()({}, key, diff));
        }

        return acc;
    }, {});
}

function getArrayChangeSet(baseArray, compareArray) {
    if (baseArray === compareArray) {
        return [];
    }

    if (!isArray(baseArray) || !isArray(compareArray)) {
        return compareArray;
    }

    if (baseArray.length === 0) {
        return compareArray;
    }

    if (compareArray.length === 0) {
        return baseArray;
    }

    var b = [].concat(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(baseArray));
    var c = [].concat(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(compareArray));

    if (!isObject(b[0]) || !isObject(c[0])) {
        return c.filter(function (value) {
            return b.indexOf(value) < 0;
        });
    }

    var diff = [];

    c.forEach(function (item, index) {
        if (!item.uuid) {
            var diffObject = getObjectChangeSet(b[index], c[index]);

            if (isObject(diffObject) && !isEmpty(diffObject)) {
                diff.push(diffObject);
            }
        } else {
            var compareObject = b.find(function (compareItem) {
                return item.uuid === compareItem.uuid;
            });

            if (!compareObject) {
                diff.push(item);
            } else {
                var _diffObject = getObjectChangeSet(compareObject, item);

                if (isObject(_diffObject) && !isEmpty(_diffObject)) {
                    diff.push(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, _diffObject, { uuid: item.uuid }));
                }
            }
        }
    });

    return diff;
}

/***/ }),

/***/ 280:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["Shopware"] = __webpack_require__(281);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 281:
/***/ (function(module, exports, __webpack_require__) {

const ModuleFactory = __webpack_require__(282);
const ComponentFactory = __webpack_require__(316);
const utils = __webpack_require__(12);
const TemplateFactory = __webpack_require__(117);
const ViewFactory = __webpack_require__(89);
const RouterFactory = __webpack_require__(90);

module.exports = {
    ModuleFactory,
    ComponentFactory,
    TemplateFactory,
    ViewFactory,
    RouterFactory,
    utils
};


/***/ }),

/***/ 282:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["ModuleFactory"] = __webpack_require__(283);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 283:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getModuleRoutes", function() { return getModuleRoutes; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "registerModule", function() { return registerModule; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getModuleRegistry", function() { return getModuleRegistry; });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_core_service_util_service__ = __webpack_require__(12);




/** @type Map modules - Registry for modules */
const modules = new Map();

/**
 * Returns the registry of all modules mounted in the application.
 *
 * @returns {Map} modules - Registry of all modules
 */
function getModuleRegistry() {
    return modules;
}

/**
 * Registers a module in the application. The module will be mounted using
 * the defined routes of the module using the router.
 *
 * @param {Object} module - Module definition - see manifest.js file
 * @param {String} [type=plugin] - Type of the module
 * @returns {Map} moduleRoutes - registered module routes
 */
function registerModule(module, type = 'plugin') {
    const moduleRoutes = new Map();
    const moduleId = module.id;

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        __WEBPACK_IMPORTED_MODULE_0_src_core_service_util_service__["default"].warn(
            'ModuleFactory',
            'Module has no unique identifier "id"',
            module
        );
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it's not accessible in the application.
    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        __WEBPACK_IMPORTED_MODULE_0_src_core_service_util_service__["default"].warn(
            'ModuleFactory',
            `Module "${moduleId}" has no configured routes. The module will not be accessible in the administration UI.`,
            module
        );
        return moduleRoutes;
    }

    // Sanitize the modules routes
    Object.keys(module.routes).forEach((routeKey) => {
        const route = module.routes[routeKey];

        // Rewrite name and path
        route.name = `${moduleId}.${routeKey}`;
        route.path = `/${type}/${route.path}`;
        route.type = type;

        const componentList = {};
        if (route.components && Object.keys(route.components).length) {
            Object.keys(route.components).forEach((componentKey) => {
                const component = route.components[componentKey];
                componentList[componentKey] = component.name;
            });

            route.components = componentList;
        } else {
            route.components = {
                default: route.component.name
            };

            // Remove the component cause we remapped it to the components object of the route object
            delete route.component;
        }

        // Alias support
        if (route.alias && route.alias.length > 0) {
            route.alias = `/${type}/${route.alias}`;
        }

        moduleRoutes.set(route.name, route);
    });

    const moduleDefinition = {
        routes: moduleRoutes,
        manifest: module
    };

    if (Object.prototype.hasOwnProperty.bind(module, 'navigation') && module.navigation) {
        moduleDefinition.navigation = module.navigation;
    }

    modules.set(moduleId, moduleDefinition);
    return moduleRoutes;
}

/**
 * Returns the defined module routes which will be registered in the router and therefore will be accessible in the
 * application.
 *
 * @returns {Array} route definitions - see {@link https://router.vuejs.org/en/essentials/named-routes.html}
 */
function getModuleRoutes() {
    const moduleRoutes = [];

    modules.forEach((module) => {
        module.routes.forEach((route) => {
            moduleRoutes.push(route);
        });
    });
    return moduleRoutes;
}


/***/ }),

/***/ 316:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["ComponentFactory"] = __webpack_require__(317);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 317:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "register", function() { return register; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "extend", function() { return extend; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "override", function() { return override; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "build", function() { return build; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getComponentTemplate", function() { return getComponentTemplate; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getComponentRegistry", function() { return getComponentRegistry; });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(35);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(20);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__ = __webpack_require__(28);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_map__ = __webpack_require__(114);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__ = __webpack_require__(12);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__ = __webpack_require__(117);









var componentRegistry = new __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_map___default.a();

var overrideRegistry = new __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_map___default.a();

function getComponentRegistry() {
    return componentRegistry;
}

function register(componentName) {
    var componentConfiguration = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    var config = componentConfiguration;

    config.name = componentName;

    if (componentRegistry.has(componentName)) {
        __WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].warn('ComponentFactory', 'The component "' + componentName + '" is already registered. Please select a unique name for your component.', config);
        return config;
    }

    config.name = componentName;

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__["default"].registerComponentTemplate(componentName, config.template);

        delete config.template;
    } else {
        __WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].warn('ComponentFactory', 'The component "' + config.name + '" needs a template to be functional.', 'Please add a "template" property to your component definition', config);
        return config;
    }

    componentRegistry.set(componentName, config);

    return config;
}

function extend(componentName, extendComponentName, componentConfiguration) {
    var config = componentConfiguration;

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__["default"].extendComponentTemplate(name, extendComponentName, config.template);

        delete config.template;
    } else {
        __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__["default"].extendComponentTemplate(componentName, extendComponentName);
    }

    config.name = componentName;
    config.extends = extendComponentName;

    componentRegistry.set(componentName, config);

    return config;
}

function override(componentName, componentConfiguration) {
    var overrideIndex = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

    var config = componentConfiguration;

    config.name = componentName;

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__["default"].registerTemplateOverride(componentName, config.template, overrideIndex);

        delete config.template;
    }

    var overrides = overrideRegistry.get(componentName) || [];

    if (overrideIndex !== null && overrideIndex >= 0 && overrides.length > 0) {
        overrides.splice(overrideIndex, 0, config);
    } else {
        overrides.push(config);
    }

    overrideRegistry.set(componentName, overrides);

    return config;
}

function getComponentTemplate(componentName) {
    return __WEBPACK_IMPORTED_MODULE_5_src_core_factory_template_factory__["default"].getRenderedTemplate(componentName);
}

function build(componentName) {
    if (!componentRegistry.has(componentName)) {
        return false;
    }

    var config = componentRegistry.get(componentName);

    if (config.extends) {
        config = getExtendedComponent(componentName);
    }

    if (overrideRegistry.has(componentName)) {
        var overrides = overrideRegistry.get(componentName);

        overrides.forEach(function (overrideComp) {
            config = mergeConfig(config, overrideComp);
        });
    }

    config.template = getComponentTemplate(componentName);

    return config;
}

function getExtendedComponent(componentName) {
    if (!componentRegistry.has(componentName)) {
        return {};
    }

    var config = componentRegistry.get(componentName);

    if (!config.extends || !componentRegistry.has(config.extends)) {
        return config;
    }

    var extendComponent = getExtendedComponent(componentRegistry.get(config.extends));

    config = mergeConfig({}, extendComponent, config);

    return config;
}

function mergeConfig(target, source) {
    if (!__WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].isObject(target) || !__WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].isObject(source)) {
        return source;
    }

    var config = __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default()({}, target);

    __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default()(source).forEach(function (key) {
        if (config.hasOwnProperty(key) && config[key] !== null) {
            if (__WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].isFunction(config[key]) && key === 'data') {
                var mergedData = mergeConfig(config[key](), source[key]());

                config[key] = function data() {
                    return mergedData;
                };
            } else if (__WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].isArray(config[key])) {
                config[key] = [].concat(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(config[key]), __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(source[key]));
            } else if (__WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].isObject(source[key])) {
                config[key] = mergeConfig(config[key], source[key]);
            } else {
                config[key] = source[key];
            }
        } else {
            config[key] = source[key];
        }
    });

    for (var _len = arguments.length, additionalSources = Array(_len > 2 ? _len - 2 : 0), _key = 2; _key < _len; _key++) {
        additionalSources[_key - 2] = arguments[_key];
    }

    if (additionalSources.length > 0) {
        return mergeConfig.apply(undefined, [config].concat(additionalSources));
    }

    return config;
}

/***/ }),

/***/ 89:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (immutable) */ __webpack_exports__["default"] = ViewFactory;
function ViewFactory(viewAdapter) {
    return {
        name: viewAdapter.getName(),
        wrapper: viewAdapter.getWrapper(),
        createInstance: viewAdapter.createInstance,
        createComponent: viewAdapter.createComponent,
        initComponents: viewAdapter.initComponents,
        getComponent: viewAdapter.getComponent,
        getComponents: viewAdapter.getComponents
    };
}

/***/ }),

/***/ 90:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (immutable) */ __webpack_exports__["default"] = createRouter;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__ = __webpack_require__(20);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(35);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__ = __webpack_require__(28);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__);



function createRouter(Router, View) {
    var allRoutes = [];
    var moduleRoutes = [];

    return {
        addRoutes: addRoutes,
        addModuleRoutes: addModuleRoutes,
        createRouterInstance: createRouterInstance,
        getViewComponent: getViewComponent
    };

    function createRouterInstance() {
        var opts = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

        var mergedRoutes = registerModuleRoutesAsChildren(allRoutes, moduleRoutes);

        var options = __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default()({}, opts, {
            routes: mergedRoutes
        });

        var router = new Router(options);

        beforeRouterInterceptor(router);
        return router;
    }

    function beforeRouterInterceptor(router) {
        router.beforeEach(function (to, from, next) {

            var moduleRegistry = Shopware.ModuleFactory.getModuleRegistry();

            var moduleNamespace = to.name.split('.');
            moduleNamespace = moduleNamespace[0] + '.' + moduleNamespace[1];

            if (!moduleRegistry.has(moduleNamespace)) {
                return next();
            }

            var module = moduleRegistry.get(moduleNamespace);
            if (!module.routes.has(to.name)) {
                return next();
            }

            to.meta.$module = module.manifest;
            return next();
        });

        return router;
    }

    function registerModuleRoutesAsChildren(core, module) {
        core.map(function (route) {
            if (route.root === true && route.coreRoute === true) {
                route.children = module;
            }

            return route;
        });

        return core;
    }

    function addModuleRoutes(routes) {
        routes.map(function (route) {
            return convertRouteComponentToViewComponent(route);
        });

        moduleRoutes = [].concat(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default()(moduleRoutes), __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default()(routes));

        return moduleRoutes;
    }

    function addRoutes(routes) {
        routes.map(function (route) {
            return convertRouteComponentToViewComponent(route);
        });

        allRoutes = [].concat(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default()(allRoutes), __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default()(routes));

        return allRoutes;
    }

    function convertRouteComponentToViewComponent(route) {
        if (Object.prototype.hasOwnProperty.call(route, 'components') && __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(route.components).length) {
            var componentList = {};

            __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(route.components).forEach(function (componentKey) {
                var component = route.components[componentKey];

                if (typeof component === 'string') {
                    component = getViewComponent(component);
                }
                componentList[componentKey] = component;
            });
            route.components = componentList;
        }

        if (typeof route.component === 'string') {
            route.component = getViewComponent(route.component);
        }

        return route;
    }

    function getViewComponent(componentName) {
        return View.getComponent(componentName);
    }
}

/***/ })

},[280]);
//# sourceMappingURL=commons.js.map