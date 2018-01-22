webpackJsonp([2],{

/***/ 14:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(58);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__ = __webpack_require__(60);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_json_stringify__ = __webpack_require__(33);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_json_stringify___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_json_stringify__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_typeof__ = __webpack_require__(41);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_typeof___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_typeof__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_core_js_array_from__ = __webpack_require__(59);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_core_js_array_from___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_babel_runtime_core_js_array_from__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty__ = __webpack_require__(86);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign__ = __webpack_require__(25);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys__ = __webpack_require__(18);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_8_uuid_v4__ = __webpack_require__(88);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_8_uuid_v4___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_8_uuid_v4__);












/* harmony default export */ __webpack_exports__["default"] = ({
    merge: merge,
    formDataToObject: formDataToObject,
    warn: warn,
    currency: currency,
    date: date,
    deepCopyObject: deepCopyObject,
    getObjectChangeSet: getObjectChangeSet,
    createId: __WEBPACK_IMPORTED_MODULE_8_uuid_v4___default.a,
    isObject: isObject,
    isPlainObject: isPlainObject,
    isEmpty: isEmpty,
    isRegExp: isRegExp,
    isArray: isArray,
    isFunction: isFunction,
    isDate: isDate,
    capitalizeString: capitalizeString,
    debounce: debounce
});

function merge(target, source) {
    __WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys___default()(source).forEach(function (key) {
        if (source[key] instanceof Object) {
            if (!target[key]) {
                __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign___default()(target, __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty___default()({}, key, {}));
            }
            __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign___default()(source[key], merge(target[key], source[key]));
        }
    });

    __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_assign___default()(target || {}, source);
    return target;
}

function formDataToObject(formData) {
    return __WEBPACK_IMPORTED_MODULE_4_babel_runtime_core_js_array_from___default()(formData).reduce(function (result, item) {
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
    var locale = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'de-DE';

    return val.toLocaleString(locale);
}

function isObject(object) {
    return object !== null && (typeof object === 'undefined' ? 'undefined' : __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_typeof___default()(object)) === 'object';
}

function isPlainObject(obj) {
    return obj.toString() === '[object Object]';
}

function isEmpty(object) {
    return __WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys___default()(object).length === 0;
}

function isRegExp(exp) {
    return exp.toString() === '[object RegExp]';
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

var debounceTimeout = void 0;

function debounce(callback, debounceTime) {
    window.clearTimeout(debounceTimeout);

    debounceTimeout = window.setTimeout(callback, debounceTime);
    return debounceTimeout;
}

function deepCopyObject() {
    var copyObject = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

    return JSON.parse(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_json_stringify___default()(copyObject));
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

    return __WEBPACK_IMPORTED_MODULE_7_babel_runtime_core_js_object_keys___default()(c).reduce(function (acc, key) {
        if (b.hasOwnProperty(key)) {
            if (isArray(b[key])) {
                var arrayDiff = getArrayChangeSet(b[key], c[key]);

                if (isArray(arrayDiff) && arrayDiff.length === 0) {
                    return acc;
                }

                return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, acc, __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty___default()({}, key, arrayDiff));
            }

            var diff = getObjectChangeSet(b[key], c[key]);

            if (isObject(diff) && isEmpty(diff) && !isDate(diff)) {
                return acc;
            }

            if (isObject(b[key]) && b[key].id) {
                diff.id = b[key].id;
            }

            return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, acc, __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_defineProperty___default()({}, key, diff));
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
        if (!item.id) {
            var diffObject = getObjectChangeSet(b[index], c[index]);

            if (isObject(diffObject) && !isEmpty(diffObject)) {
                diff.push(diffObject);
            }
        } else {
            var compareObject = b.find(function (compareItem) {
                return item.id === compareItem.id;
            });

            if (!compareObject) {
                diff.push(item);
            } else {
                var _diffObject = getObjectChangeSet(compareObject, item);

                if (isObject(_diffObject) && !isEmpty(_diffObject)) {
                    diff.push(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default()({}, _diffObject, { id: item.id }));
                }
            }
        }
    });

    return diff;
}

function capitalizeString(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/***/ }),

/***/ 222:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["Shopware"] = __webpack_require__(223);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(22)))

/***/ }),

/***/ 223:
/***/ (function(module, exports, __webpack_require__) {

/**
 * Shopware End Developer API
 * @module Shopware
 * @ignore
 */

// <reference path="types/common.d.ts" />
const Bottle = __webpack_require__(224);

const ModuleFactory = __webpack_require__(225).default;
const ComponentFactory = __webpack_require__(268).default;
const TemplateFactory = __webpack_require__(90).default;
const EntityFactory = __webpack_require__(272).default;
const StateFactory = __webpack_require__(273).default;
const MixinFactory = __webpack_require__(274).default;

const utils = __webpack_require__(14).default;
const ApplicationBootstrapper = __webpack_require__(275).default;

const container = new Bottle({
    strict: true
});

const application = new ApplicationBootstrapper(container);

application
    .addFactory('component', () => {
        return ComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('entity', () => {
        return EntityFactory;
    })
    .addFactory('state', () => {
        return StateFactory;
    })
    .addFactory('mixin', () => {
        return MixinFactory;
    });

module.exports = {
    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Module: {
        register: ModuleFactory.registerModule
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Entity: {
        addDefinition: EntityFactory.addEntityDefinition,
        getDefinition: EntityFactory.getEntityDefinition,
        getDefinitionRegistry: EntityFactory.getDefinitionRegistry,
        getRawEntityObject: EntityFactory.getRawEntityObject,
        getRawDefinition: EntityFactory.getRawDefinition,
        getRequiredProperties: EntityFactory.getRequiredProperties
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    State: {
        register: StateFactory.registerStateModule
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Mixin: {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName
    },

    /**
     * @memberOf module:Shopware
     * @type {module:core/service/utils}
     */
    Utils: utils,

    /**
     * @memberOf module:Shopware
     * @type {module:core/application}
     */
    Application: application
};


/***/ }),

/***/ 225:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__ = __webpack_require__(18);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__ = __webpack_require__(14);





/* harmony default export */ __webpack_exports__["default"] = ({
    getModuleRoutes: getModuleRoutes,
    registerModule: registerModule,
    getModuleRegistry: getModuleRegistry
});

var modules = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();

function getModuleRegistry() {
    return modules;
}

function registerModule(moduleId, module) {
    var moduleRoutes = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();
    var type = module.type || 'plugin';

    if (!moduleId) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'Module has no unique identifier "id". Abort registration.', module);
        return false;
    }

    if (modules.has(moduleId)) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'A module with the identifier "' + moduleId + '" is registered already. Abort registration.', modules.get(moduleId));

        return false;
    }

    var splitModuleId = moduleId.split('-');

    if (splitModuleId.length < 2) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'Module identifier does not match the necessary format "[namespace]-[name]":', moduleId, 'Abort registration.');
        return false;
    }

    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'Module "' + moduleId + '" has no configured routes. The module will not be accessible in the administration UI.', 'Abort registration.', module);
        return false;
    }

    __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(module.routes).forEach(function (routeKey) {
        var route = module.routes[routeKey];

        route.name = splitModuleId.join('.') + '.' + routeKey;

        if (!route.coreRoute) {
            route.path = '/' + splitModuleId.join('/') + '/' + route.path;
        }
        route.type = type;

        var componentList = {};
        if (route.components && __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(route.components).length) {
            __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(route.components).forEach(function (componentKey) {
                var component = route.components[componentKey];

                if (!component.length || component.length <= 0) {
                    __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'The route definition of module "' + moduleId + '" is not valid. \n                        A route needs an assigned component name.');
                    return;
                }

                componentList[componentKey] = component;
            });

            route.components = componentList;
        } else {
            if (!route.component || !route.component.length) {
                __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'The route definition of module "' + moduleId + '" is not valid. \n                    A route needs an assigned component name.');
                return;
            }

            route.components = {
                default: route.component
            };

            delete route.component;
        }

        if (route.alias && route.alias.length > 0 && !route.coreRoute) {
            route.alias = '/' + splitModuleId.join('/') + '/' + route.alias;
        }

        moduleRoutes.set(route.name, route);
    });

    if (moduleRoutes.size === 0) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ModuleFactory', 'The module "' + moduleId + '" was not registered cause it hasn\'t a valid route definition', 'Abort registration.', module.routes);
        return false;
    }

    var moduleDefinition = {
        routes: moduleRoutes,
        manifest: module,
        type: type
    };

    if (Object.prototype.hasOwnProperty.bind(module, 'navigation') && module.navigation) {
        moduleDefinition.navigation = module.navigation;
    }

    modules.set(moduleId, moduleDefinition);

    return moduleDefinition;
}

function getModuleRoutes() {
    var moduleRoutes = [];

    modules.forEach(function (module) {
        module.routes.forEach(function (route) {
            moduleRoutes.push(route);
        });
    });

    return moduleRoutes;
}

/***/ }),

/***/ 268:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create__ = __webpack_require__(89);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__ = __webpack_require__(14);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__ = __webpack_require__(90);






/* harmony default export */ __webpack_exports__["default"] = ({
    register: register,
    extend: extend,
    override: override,
    build: build,
    getComponentTemplate: getComponentTemplate,
    getComponentRegistry: getComponentRegistry,
    getOverrideRegistry: getOverrideRegistry
});

var componentRegistry = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();

var overrideRegistry = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();

function getComponentRegistry() {
    return componentRegistry;
}

function getOverrideRegistry() {
    return overrideRegistry;
}

function register(componentName) {
    var componentConfiguration = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    var config = componentConfiguration;

    if (!componentName || !componentName.length) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ComponentFactory', 'A component always needs a name.', componentConfiguration);
        return false;
    }

    if (componentRegistry.has(componentName)) {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ComponentFactory', 'The component "' + componentName + '" is already registered. Please select a unique name for your component.', config);
        return false;
    }

    config.name = componentName;

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].registerComponentTemplate(componentName, config.template);

        delete config.template;
    } else {
        __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].warn('ComponentFactory', 'The component "' + config.name + '" needs a template to be functional.', 'Please add a "template" property to your component definition', config);
        return false;
    }

    componentRegistry.set(componentName, config);

    return config;
}

function extend(componentName, extendComponentName, componentConfiguration) {
    var config = componentConfiguration;

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].extendComponentTemplate(componentName, extendComponentName, config.template);

        delete config.template;
    } else {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].extendComponentTemplate(componentName, extendComponentName);
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
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].registerTemplateOverride(componentName, config.template, overrideIndex);

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
    return __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].getRenderedTemplate(componentName);
}

function build(componentName) {
    var skipTemplate = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;

    if (!componentRegistry.has(componentName)) {
        return false;
    }

    var config = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create___default()(componentRegistry.get(componentName));

    if (config.extends) {
        var extendComp = build(config.extends, true);

        if (extendComp) {
            config.extends = extendComp;
        } else {
            delete config.extends;
        }
    }

    if (overrideRegistry.has(componentName)) {
        var overrides = overrideRegistry.get(componentName);

        overrides.forEach(function (overrideComp) {
            var comp = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create___default()(overrideComp);

            comp.extends = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_create___default()(config);
            config = comp;
        });
    }

    if (skipTemplate !== true) {
        config.template = getComponentTemplate(componentName);
    } else {
        delete config.template;
    }

    return config;
}

/***/ }),

/***/ 272:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__ = __webpack_require__(18);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__);



/* harmony default export */ __webpack_exports__["default"] = ({
    addEntityDefinition: addEntityDefinition,
    getEntityDefinition: getEntityDefinition,
    getDefinitionRegistry: getDefinitionRegistry,
    getRawEntityObject: getRawEntityObject,
    getRawDefinition: getRawDefinition,
    getRequiredProperties: getRequiredProperties
});

var entityDefinitions = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();

function addEntityDefinition(entityName) {
    var entityDefinition = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    if (!entityName || !entityName.length) {
        return false;
    }

    entityDefinitions.set(entityName, entityDefinition);
    return true;
}

function getEntityDefinition(entityName) {
    return entityDefinitions.get(entityName);
}

function getDefinitionRegistry() {
    return entityDefinitions;
}

function getRawEntityObject(entityName) {
    var includeObjectAssociations = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;

    if (!entityDefinitions.has(entityName)) {
        return {};
    }

    var definition = entityDefinitions.get(entityName);
    var entity = {};

    __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(definition.properties).forEach(function (propertyName) {
        var property = definition.properties[propertyName];

        if (property.type === 'array') {
            entity[propertyName] = [];
        } else if (property.type === 'object') {
            if (property.entity && includeObjectAssociations) {
                entity[propertyName] = getRawEntityObject(property.entity);
            } else {
                entity[propertyName] = {};
            }
        } else if (property.type === 'boolean') {
            entity[propertyName] = false;
        } else if (property.type === 'string') {
            entity[propertyName] = '';
        } else if (property.type === 'number' || property.type === 'integer') {
            entity[propertyName] = 0;
        }
    });

    return entity;
}

function getRawDefinition(entityName) {
    if (!entityDefinitions.has(entityName)) {
        return {};
    }

    var definition = entityDefinitions.get(entityName);
    var entity = {};

    __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default()(definition.properties).forEach(function (propertyName) {
        var property = definition.properties[propertyName];

        if (property.type === 'array') {
            entity[entityName] = Array;
        } else if (property.type === 'object') {
            entity[entityName] = Object;
        } else if (property.type === 'boolean') {
            entity[entityName] = Boolean;
        } else if (property.type === 'string') {
            entity[entityName] = String;
        } else if (property.type === 'number' || property.type === 'integer') {
            entity[entityName] = Number;
        }
    });

    return entity;
}

function getRequiredProperties(entityName) {
    if (!entityDefinitions.has(entityName)) {
        return [];
    }

    var definition = entityDefinitions.get(entityName);
    return definition.required;
}

/***/ }),

/***/ 273:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__ = __webpack_require__(14);




/* harmony default export */ __webpack_exports__["default"] = ({
    registerStateModule: registerStateModule,
    getStateModule: getStateModule,
    getStateRegistry: getStateRegistry
});

var stateRegistry = new __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map___default.a();

function registerStateModule(name) {
    var module = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    if (!name || !name.length) {
        __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].warn('SateFactory', 'A state module always needs a name.', module);
        return false;
    }

    if (stateRegistry.has(name)) {
        __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].warn('SateFactory', 'A state module with the name ' + name + ' already exists.', module);
        return false;
    }

    stateRegistry.set(name, module);

    return true;
}

function getStateModule(name) {
    return stateRegistry.get(name);
}

function getStateRegistry() {
    return stateRegistry;
}

/***/ }),

/***/ 274:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__ = __webpack_require__(14);




/* harmony default export */ __webpack_exports__["default"] = ({
    register: register,
    getByName: getByName,
    getMixinRegistry: getMixinRegistry
});

var mixinRegistry = new __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_map___default.a();

function getMixinRegistry() {
    return mixinRegistry;
}

function register(mixinName) {
    var mixin = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    if (!mixinName || !mixinName.length) {
        __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].warn('MixinFactory', 'A mixin always needs a name.', mixin);
        return false;
    }

    if (mixinRegistry.has(mixinName)) {
        __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].warn('MixinFactory', 'The mixin "' + mixinName + '" is already registered. Please select a unique name for your mixin.', mixin);
        return false;
    }

    mixinRegistry.set(mixinName, mixin);

    return mixin;
}

function getByName(mixinName) {
    return mixinRegistry.get(mixinName);
}

/***/ }),

/***/ 275:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_classCallCheck__ = __webpack_require__(2);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_createClass__ = __webpack_require__(34);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_createClass___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_createClass__);



var ApplicationBootstrapper = function () {
    function ApplicationBootstrapper(container) {
        __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_classCallCheck___default()(this, ApplicationBootstrapper);

        var noop = function noop() {};
        this.$container = container;

        this.$container.service('service', noop);
        this.$container.service('init', noop);
        this.$container.service('factory', noop);
    }

    __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_createClass___default()(ApplicationBootstrapper, [{
        key: 'getContainer',
        value: function getContainer(containerName) {
            var containerNames = this.$container.list();

            if (containerNames.indexOf(containerName) !== -1) {
                return this.$container.container[containerName];
            }
            return this.$container.container;
        }
    }, {
        key: 'addFactory',
        value: function addFactory(name, factory) {
            this.$container.factory('factory.' + name, factory.bind(this));

            return this;
        }
    }, {
        key: 'addFactoryMiddleware',
        value: function addFactoryMiddleware() {
            for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
                args[_key] = arguments[_key];
            }

            return this._addMiddleware('factory', args);
        }
    }, {
        key: 'addFactoryDecorator',
        value: function addFactoryDecorator() {
            for (var _len2 = arguments.length, args = Array(_len2), _key2 = 0; _key2 < _len2; _key2++) {
                args[_key2] = arguments[_key2];
            }

            return this._addDecorator('factory', args);
        }
    }, {
        key: 'addInitializer',
        value: function addInitializer(name, initializer) {
            this.$container.factory('init.' + name, initializer.bind(this));
            return this;
        }
    }, {
        key: 'addServiceProvider',
        value: function addServiceProvider(name, provider) {
            this.$container.factory('service.' + name, provider.bind(this));
            return this;
        }
    }, {
        key: 'registerContext',
        value: function registerContext(context) {
            return this.addInitializer('context', function () {
                return context;
            });
        }
    }, {
        key: 'addInitializerMiddleware',
        value: function addInitializerMiddleware() {
            for (var _len3 = arguments.length, args = Array(_len3), _key3 = 0; _key3 < _len3; _key3++) {
                args[_key3] = arguments[_key3];
            }

            return this._addMiddleware('init', args);
        }
    }, {
        key: 'addServiceProviderMiddleware',
        value: function addServiceProviderMiddleware() {
            for (var _len4 = arguments.length, args = Array(_len4), _key4 = 0; _key4 < _len4; _key4++) {
                args[_key4] = arguments[_key4];
            }

            return this._addMiddleware('service', args);
        }
    }, {
        key: '_addMiddleware',
        value: function _addMiddleware(containerName, args) {
            var name = args.length > 1 ? containerName + '.' + args[0] : containerName;
            var middlewareFn = args.length > 1 ? args[1] : args[0];

            this.$container.middleware(name, middlewareFn);

            return this;
        }
    }, {
        key: 'addInitializerDecorator',
        value: function addInitializerDecorator() {
            for (var _len5 = arguments.length, args = Array(_len5), _key5 = 0; _key5 < _len5; _key5++) {
                args[_key5] = arguments[_key5];
            }

            return this._addDecorator('init', args);
        }
    }, {
        key: 'addServiceProviderDecorator',
        value: function addServiceProviderDecorator() {
            for (var _len6 = arguments.length, args = Array(_len6), _key6 = 0; _key6 < _len6; _key6++) {
                args[_key6] = arguments[_key6];
            }

            return this._addDecorator('service', args);
        }
    }, {
        key: '_addDecorator',
        value: function _addDecorator(containerName, args) {
            var name = args.length > 1 ? containerName + '.' + args[0] : containerName;
            var middlewareFn = args.length > 1 ? args[1] : args[0];

            this.$container.decorator(name, middlewareFn);

            return this;
        }
    }, {
        key: 'start',
        value: function start() {
            var context = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

            this.registerContext(context).createApplicationRoot();
        }
    }, {
        key: 'getApplicationRoot',
        value: function getApplicationRoot() {
            if (!this.applicationRoot) {
                return false;
            }

            return this.applicationRoot;
        }
    }, {
        key: 'createApplicationRoot',
        value: function createApplicationRoot() {
            var _this = this;

            var container = this.getContainer('init');
            var router = container.router;
            var view = container.view;

            container.entity.then(function () {
                _this.applicationRoot = view.createInstance('#app', router, _this.getContainer('service'));
            });

            return this;
        }
    }]);

    return ApplicationBootstrapper;
}();

/* harmony default export */ __webpack_exports__["default"] = (ApplicationBootstrapper);

/***/ }),

/***/ 90:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__ = __webpack_require__(25);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(18);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_twig__ = __webpack_require__(271);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__ = __webpack_require__(14);







/* harmony default export */ __webpack_exports__["default"] = ({
    registerComponentTemplate: registerComponentTemplate,
    extendComponentTemplate: extendComponentTemplate,
    registerTemplateOverride: registerTemplateOverride,
    getRenderedTemplate: getRenderedTemplate,
    getTemplateOverrides: getTemplateOverrides,
    getTemplateRegistry: getTemplateRegistry,
    findCustomTemplate: findCustomTemplate,
    findCustomOverride: findCustomOverride,
    clearTwigCache: clearTwigCache,
    getTwigCache: getTwigCache,
    disableTwigCache: disableTwigCache
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

    TwigCore.exports.getRegistry = function getRegistry() {
        return TwigCore.Templates.registry;
    };

    TwigCore.exports.clearRegistry = function clearRegistry() {
        TwigCore.Templates.registry = {};
    };
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

    try {
        template.baseTemplate = __WEBPACK_IMPORTED_MODULE_3_twig___default.a.twig(templateConfig);
    } catch (error) {
        __WEBPACK_IMPORTED_MODULE_4_src_core_service_util_service__["default"].warn(error.message);
        return false;
    }

    templateRegistry.set(componentName, template);
    return true;
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

function clearTwigCache() {
    __WEBPACK_IMPORTED_MODULE_3_twig___default.a.clearRegistry();
}

function getTwigCache() {
    return __WEBPACK_IMPORTED_MODULE_3_twig___default.a.getRegistry();
}

function disableTwigCache() {
    __WEBPACK_IMPORTED_MODULE_3_twig___default.a.cache(false);
}

/***/ }),

/***/ 91:
/***/ (function(module, exports) {

/* (ignored) */

/***/ })

},[222]);
//# sourceMappingURL=commons.js.map