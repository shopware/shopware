webpackJsonp([2],{

/***/ 101:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__ = __webpack_require__(19);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(30);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__ = __webpack_require__(86);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_twig__ = __webpack_require__(193);
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

/***/ 102:
/***/ (function(module, exports) {

/* (ignored) */

/***/ }),

/***/ 147:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["Shopware"] = __webpack_require__(148);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 148:
/***/ (function(module, exports, __webpack_require__) {

const ModuleFactory = __webpack_require__(149);
const ComponentFactory = __webpack_require__(151);
const utils = __webpack_require__(29);
const TemplateFactory = __webpack_require__(101);
const ViewFactory = __webpack_require__(72);
const RouterFactory = __webpack_require__(73);

module.exports = {
    ModuleFactory,
    ComponentFactory,
    TemplateFactory,
    ViewFactory,
    RouterFactory,
    utils
};


/***/ }),

/***/ 149:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["ModuleFactory"] = __webpack_require__(150);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 150:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getModuleRoutes", function() { return getModuleRoutes; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "registerModule", function() { return registerModule; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getModuleRegistry", function() { return getModuleRegistry; });


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
 * @returns {Array} registered module routes
 */
function registerModule(module, type = 'plugin') {
    const moduleRoutes = [];
    const moduleId = module.id;

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        console.warn('[module.factory] Module has no unique identifier', module);
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it's not accessible in the application.
    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        console.warn(
            `[module.factory] Module "${moduleId}" has no defined routes. The module will not be accessible.`,
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
        route.component = route.component.name;

        // Alias support
        if (route.alias && route.alias.length > 0) {
            route.alias = `/${type}/${route.alias}`;
        }

        moduleRoutes.push(route);
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

/***/ 151:
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["ComponentFactory"] = __webpack_require__(152);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(24)))

/***/ }),

/***/ 152:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "register", function() { return register; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "extend", function() { return extend; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "override", function() { return override; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getComponentTemplate", function() { return getComponentTemplate; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "getComponentRegistry", function() { return getComponentRegistry; });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__ = __webpack_require__(19);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__ = __webpack_require__(86);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__ = __webpack_require__(29);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__ = __webpack_require__(101);







var componentRegistry = new __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_map___default.a();

function getComponentRegistry() {
    return componentRegistry;
}

function register(componentName, componentConfiguration) {
    var config = componentConfiguration;

    config = __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].merge(config, {
        name: componentName
    });

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].registerComponentTemplate(componentName, config.template);

        delete config.template;
    }

    componentRegistry.set(componentName, config);

    return config;
}

function extend(componentName, extendComponentName, componentConfiguration) {
    var config = componentConfiguration;

    if (!componentRegistry.has(extendComponentName)) {
        return register(componentName, config);
    }

    var name = componentName;
    var extendedComponent = componentRegistry.get(extendComponentName);

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].extendComponentTemplate(name, extendComponentName, config.template);

        delete config.template;
    } else {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].extendComponentTemplate(componentName, extendComponentName);
    }

    config = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default()(config, {
        name: componentName
    });

    config = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default()({}, extendedComponent, config);

    componentRegistry.set(componentName, config);

    return config;
}

function override(componentName, componentConfiguration) {
    var overrideIndex = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

    var config = componentConfiguration;

    if (!componentRegistry.has(componentName)) {
        return register(componentName, config);
    }

    config = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default()(config, {
        name: componentName
    });

    if (config.template) {
        __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].registerTemplateOverride(componentName, config.template, overrideIndex);

        delete config.template;
    }

    config = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_assign___default()({}, componentRegistry.get(componentName), config);

    componentRegistry.set(componentName, config);

    return config;
}

function getComponentTemplate(componentName) {
    return __WEBPACK_IMPORTED_MODULE_3_src_core_factory_template_factory__["default"].getRenderedTemplate(componentName);
}

/***/ }),

/***/ 29:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_array_from__ = __webpack_require__(69);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_array_from___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_array_from__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_defineProperty__ = __webpack_require__(70);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_defineProperty___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_defineProperty__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__ = __webpack_require__(19);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_object_keys__ = __webpack_require__(30);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_object_keys__);




/* harmony default export */ __webpack_exports__["default"] = ({
    merge: merge,
    formDataToObject: formDataToObject
});

function merge(target, source) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_object_keys___default()(source).forEach(function (key) {
        if (source[key] instanceof Object) {
            if (!target[key]) {
                __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default()(target, __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_defineProperty___default()({}, key, {}));
            }
            __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default()(source[key], merge(target[key], source[key]));
        }
    });

    __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign___default()(target || {}, source);
    return target;
}

function formDataToObject(formData) {
    return __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_array_from___default()(formData).reduce(function (result, item) {
        result[item[0]] = item[1];
        return result;
    }, {});
}

/***/ }),

/***/ 72:
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

/***/ 73:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony export (immutable) */ __webpack_exports__["default"] = createRouter;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(74);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign__ = __webpack_require__(19);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign__);


function createRouter(Router, View) {
    var allRoutes = [];
    var moduleRoutes = [];

    return {
        addRoutes: addRoutes,
        addModuleRoutes: addModuleRoutes,
        createRouterInstance: createRouterInstance,
        getViewComponent: getViewComponent
    };

    function createRouterInstance(opts) {
        var mergedRoutes = registerModuleRoutesAsChildren(allRoutes, moduleRoutes);

        var options = __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign___default()({}, opts, {
            routes: mergedRoutes
        });

        var router = new Router(options);

        return router;
    }

    function registerModuleRoutesAsChildren(core, module) {
        core.forEach(function (route) {
            if (route.root === true && route.coreRoute === true) {
                route.children = module;
            }
        });

        return core;
    }

    function addModuleRoutes(routes) {
        routes.forEach(function (route) {
            if (typeof route.component === 'string') {
                route.component = getViewComponent(route.component);
            }
        });

        moduleRoutes = [].concat(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(moduleRoutes), __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(routes));

        return moduleRoutes;
    }

    function addRoutes(routes) {
        routes.forEach(function (route) {
            if (typeof route.component === 'string') {
                route.component = getViewComponent(route.component);
            }
        });

        allRoutes = [].concat(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(allRoutes), __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default()(routes));

        return allRoutes;
    }

    function getViewComponent(componentName) {
        return View.getComponent(componentName);
    }
}

/***/ })

},[147]);
//# sourceMappingURL=commons.js.map