webpackJsonp([1],[
/* 0 */,
/* 1 */,
/* 2 */,
/* 3 */,
/* 4 */,
/* 5 */,
/* 6 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_promise__ = __webpack_require__(43);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_promise___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_promise__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_createClass__ = __webpack_require__(32);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_createClass___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_createClass__);




var ApiService = function () {
    function ApiService(httpClient, apiEndpoint) {
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, ApiService);

        this.httpClient = httpClient;
        this.apiEndpoint = apiEndpoint;
        this.returnFormat = returnFormat;
    }

    __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_createClass___default()(ApiService, [{
        key: 'getList',
        value: function getList() {
            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 0;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 25;

            return this.httpClient.get(this.getApiBasePath() + '?offset=' + offset + '&limit=' + limit).then(function (response) {
                return response.data;
            });
        }
    }, {
        key: 'getByUuid',
        value: function getByUuid(uuid) {
            if (!uuid) {
                return __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_promise___default.a.reject(new Error('Missing required argument: uuid'));
            }

            return this.httpClient.get(this.getApiBasePath(uuid)).then(function (response) {
                return response.data;
            });
        }
    }, {
        key: 'updateByUuid',
        value: function updateByUuid(uuid, payload) {
            if (!uuid) {
                return __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_promise___default.a.reject(new Error('Missing required argument: uuid'));
            }

            return this.httpClient.patch(this.getApiBasePath(uuid), payload).then(function (response) {
                return response.data;
            });
        }
    }, {
        key: 'create',
        value: function create(payload) {
            return this.httpClient.post(this.getApiBasePath(), payload).then(function (response) {
                return response.data;
            });
        }
    }, {
        key: 'getApiBasePath',
        value: function getApiBasePath(uuid) {
            var returnFormat = this.returnFormat.length ? '.' + this.returnFormat : '';

            if (uuid && uuid.length > 0) {
                return this.apiEndpoint + '/' + uuid + ')' + returnFormat;
            }

            return '' + this.apiEndpoint + returnFormat;
        }
    }, {
        key: 'apiEndpoint',
        get: function get() {
            return this.endpoint;
        },
        set: function set(endpoint) {
            this.endpoint = endpoint;
        }
    }, {
        key: 'httpClient',
        get: function get() {
            return this.client;
        },
        set: function set(client) {
            this.client = client;
        }
    }, {
        key: 'returnFormat',
        get: function get() {
            return this.format;
        },
        set: function set(format) {
            this.format = format;
        }
    }]);

    return ApiService;
}();

/* harmony default export */ __webpack_exports__["a"] = (ApiService);

/***/ }),
/* 7 */,
/* 8 */,
/* 9 */,
/* 10 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(38);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__ = __webpack_require__(39);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_extends__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof__ = __webpack_require__(41);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_typeof__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from__ = __webpack_require__(60);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_core_js_array_from__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty__ = __webpack_require__(64);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_defineProperty__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_5_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys__ = __webpack_require__(17);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_6_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_uuid_v4__ = __webpack_require__(87);
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
/* 11 */,
/* 12 */,
/* 13 */,
/* 14 */,
/* 15 */,
/* 16 */,
/* 17 */,
/* 18 */,
/* 19 */,
/* 20 */,
/* 21 */,
/* 22 */,
/* 23 */,
/* 24 */,
/* 25 */,
/* 26 */,
/* 27 */,
/* 28 */,
/* 29 */,
/* 30 */,
/* 31 */,
/* 32 */,
/* 33 */,
/* 34 */,
/* 35 */,
/* 36 */,
/* 37 */,
/* 38 */,
/* 39 */,
/* 40 */,
/* 41 */,
/* 42 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony default export */ __webpack_exports__["a"] = ({
    data: function data() {
        return {
            page: 1,
            limit: 25,
            total: 0
        };
    },


    computed: {
        maxPage: function maxPage() {
            return Math.ceil(this.total / this.limit);
        },
        offset: function offset() {
            return (this.page - 1) * this.limit;
        }
    },

    methods: {
        pageChange: function pageChange(opts) {
            if (opts.page) {
                this.page = opts.page;
            }

            if (opts.limit) {
                this.limit = opts.limit;
            }

            this.handlePagination(this.offset, this.limit, this.page);
        }
    }
});

/***/ }),
/* 43 */,
/* 44 */,
/* 45 */,
/* 46 */,
/* 47 */,
/* 48 */,
/* 49 */,
/* 50 */,
/* 51 */,
/* 52 */,
/* 53 */,
/* 54 */,
/* 55 */,
/* 56 */,
/* 57 */,
/* 58 */,
/* 59 */,
/* 60 */,
/* 61 */,
/* 62 */,
/* 63 */,
/* 64 */,
/* 65 */,
/* 66 */,
/* 67 */,
/* 68 */,
/* 69 */,
/* 70 */,
/* 71 */,
/* 72 */,
/* 73 */,
/* 74 */,
/* 75 */,
/* 76 */,
/* 77 */,
/* 78 */,
/* 79 */,
/* 80 */,
/* 81 */,
/* 82 */,
/* 83 */,
/* 84 */,
/* 85 */,
/* 86 */,
/* 87 */,
/* 88 */,
/* 89 */,
/* 90 */,
/* 91 */,
/* 92 */,
/* 93 */,
/* 94 */,
/* 95 */,
/* 96 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__ = __webpack_require__(67);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign__ = __webpack_require__(21);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_createClass__ = __webpack_require__(32);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_createClass___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_createClass__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__service_util_service__ = __webpack_require__(10);






/* harmony default export */ __webpack_exports__["a"] = ({
    create: createProxy
});

var DataProxy = function () {
    function DataProxy(data) {
        __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_classCallCheck___default()(this, DataProxy);

        this.originalData = deepCopy(data);
        this.processedData = deepCopy(data);

        this.versions = [];
    }

    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_createClass___default()(DataProxy, [{
        key: 'data',
        get: function get() {
            return this.processedData;
        },
        set: function set(data) {
            this.versions.push(deepCopy(this.originalData));

            this.originalData = deepCopy(data);

            if (data.updatedAt) {
                delete data.updatedAt;
            }

            this.processedData = __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_assign___default()(this.processedData, data);
        }
    }, {
        key: 'changeSet',
        get: function get() {
            return __WEBPACK_IMPORTED_MODULE_4__service_util_service__["default"].getObjectChangeSet(this.originalData, this.processedData);
        }
    }]);

    return DataProxy;
}();

function createProxy(data) {
    return new DataProxy(data);
}

function deepCopy(data) {
    return JSON.parse(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default()(data));
}

/***/ }),
/* 97 */,
/* 98 */,
/* 99 */,
/* 100 */,
/* 101 */,
/* 102 */,
/* 103 */,
/* 104 */,
/* 105 */,
/* 106 */,
/* 107 */,
/* 108 */,
/* 109 */,
/* 110 */,
/* 111 */,
/* 112 */,
/* 113 */,
/* 114 */,
/* 115 */,
/* 116 */,
/* 117 */,
/* 118 */,
/* 119 */,
/* 120 */,
/* 121 */,
/* 122 */,
/* 123 */,
/* 124 */,
/* 125 */,
/* 126 */,
/* 127 */,
/* 128 */,
/* 129 */,
/* 130 */,
/* 131 */,
/* 132 */,
/* 133 */,
/* 134 */,
/* 135 */,
/* 136 */,
/* 137 */,
/* 138 */,
/* 139 */,
/* 140 */,
/* 141 */,
/* 142 */,
/* 143 */,
/* 144 */,
/* 145 */,
/* 146 */,
/* 147 */,
/* 148 */,
/* 149 */,
/* 150 */,
/* 151 */,
/* 152 */,
/* 153 */,
/* 154 */,
/* 155 */,
/* 156 */,
/* 157 */,
/* 158 */,
/* 159 */,
/* 160 */,
/* 161 */,
/* 162 */,
/* 163 */,
/* 164 */,
/* 165 */,
/* 166 */,
/* 167 */,
/* 168 */,
/* 169 */,
/* 170 */,
/* 171 */,
/* 172 */,
/* 173 */,
/* 174 */,
/* 175 */,
/* 176 */,
/* 177 */,
/* 178 */,
/* 179 */,
/* 180 */,
/* 181 */,
/* 182 */,
/* 183 */,
/* 184 */,
/* 185 */,
/* 186 */,
/* 187 */,
/* 188 */,
/* 189 */,
/* 190 */,
/* 191 */,
/* 192 */,
/* 193 */,
/* 194 */,
/* 195 */,
/* 196 */,
/* 197 */,
/* 198 */,
/* 199 */,
/* 200 */,
/* 201 */,
/* 202 */,
/* 203 */,
/* 204 */,
/* 205 */,
/* 206 */,
/* 207 */,
/* 208 */,
/* 209 */,
/* 210 */,
/* 211 */,
/* 212 */,
/* 213 */,
/* 214 */,
/* 215 */,
/* 216 */,
/* 217 */,
/* 218 */,
/* 219 */,
/* 220 */,
/* 221 */,
/* 222 */,
/* 223 */,
/* 224 */,
/* 225 */,
/* 226 */,
/* 227 */,
/* 228 */,
/* 229 */,
/* 230 */,
/* 231 */,
/* 232 */,
/* 233 */,
/* 234 */,
/* 235 */,
/* 236 */,
/* 237 */,
/* 238 */,
/* 239 */,
/* 240 */,
/* 241 */,
/* 242 */,
/* 243 */,
/* 244 */,
/* 245 */,
/* 246 */,
/* 247 */,
/* 248 */,
/* 249 */,
/* 250 */,
/* 251 */,
/* 252 */,
/* 253 */,
/* 254 */,
/* 255 */,
/* 256 */,
/* 257 */,
/* 258 */,
/* 259 */,
/* 260 */,
/* 261 */,
/* 262 */,
/* 263 */,
/* 264 */,
/* 265 */,
/* 266 */,
/* 267 */,
/* 268 */,
/* 269 */
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {module.exports = global["ShopwareApplication"] = __webpack_require__(270);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(22)))

/***/ }),
/* 270 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_init_context_init__ = __webpack_require__(271);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_init_http_init__ = __webpack_require__(273);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_app_init_modules_init__ = __webpack_require__(294);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_src_app_init_view_init__ = __webpack_require__(342);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_src_app_init_router_init__ = __webpack_require__(394);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_src_app_service_menu_service__ = __webpack_require__(397);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_src_core_service_api__ = __webpack_require__(398);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_src_app_assets_less_all_less__ = __webpack_require__(424);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_src_app_assets_less_all_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_7_src_app_assets_less_all_less__);
/** Initializers */






/** Services */



/** Import global styles */


const application = Shopware.Application;

application
    .addInitializer('contextService', __WEBPACK_IMPORTED_MODULE_0_src_app_init_context_init__["a" /* default */])
    .addInitializer('httpClient', __WEBPACK_IMPORTED_MODULE_1_src_app_init_http_init__["a" /* default */])
    .addInitializer('coreModuleRoutes', __WEBPACK_IMPORTED_MODULE_2_src_app_init_modules_init__["a" /* default */])
    .addInitializer('view', __WEBPACK_IMPORTED_MODULE_3_src_app_init_view_init__["a" /* default */])
    .addInitializer('router', __WEBPACK_IMPORTED_MODULE_4_src_app_init_router_init__["a" /* default */])
    .addServiceProvider('menuService', () => {
        return Object(__WEBPACK_IMPORTED_MODULE_5_src_app_service_menu_service__["a" /* default */])();
    });

// Loop through the api services and register them as service providers in the application
__WEBPACK_IMPORTED_MODULE_6_src_core_service_api__["a" /* default */].forEach((service) => {
    const ServiceFactoryClass = service.provider;
    const name = service.name;

    application.addServiceProvider(name, () => {
        const initContainer = application.$container.container.init;
        return new ServiceFactoryClass(initContainer.httpClient);
    });
});

// When we're working with the hot module replacement server we wanna start up the application right away, we're
// ignoring the code coverage for it cause we'll never hit the hot module reloading mode with unit tests.

/* istanbul ignore if */
if (false) {
    application.start();
}


/***/ }),
/* 271 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = initializeContext;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_core_factory_context_factory__ = __webpack_require__(272);


function initializeContext(container) {
  return Object(__WEBPACK_IMPORTED_MODULE_0_src_core_factory_context_factory__["a" /* default */])(container.context);
}

/***/ }),
/* 272 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = createContext;
function createContext(context) {
    var isDevMode = "production" !== 'production';
    var installationPath = getInstallationPath(context, isDevMode);

    return {
        installationPath: installationPath,
        apiPath: getApiPath(installationPath, isDevMode),
        assetsPath: getAssetsPath(installationPath, isDevMode)
    };
}

function getInstallationPath(context, isDevMode) {
    if (isDevMode) {
        return '';
    }

    var fullPath = '';
    if (context.schemeAndHttpHost && context.schemeAndHttpHost.length) {
        fullPath = '' + context.schemeAndHttpHost + context.basePath;
    }

    return fullPath;
}

function getApiPath(installationPath, isDevMode) {
    if (isDevMode) {
        installationPath = Object({"NODE_ENV":"production"}).BASE_PATH;
    }

    return installationPath + '/api';
}

function getAssetsPath(installationPath, isDevMode) {
    if (isDevMode) {
        return '';
    }

    return installationPath + '/bundles/administration';
}

/***/ }),
/* 273 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = initializeHttpClient;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_core_factory_http_factory__ = __webpack_require__(274);



function initializeHttpClient(container) {
    return Object(__WEBPACK_IMPORTED_MODULE_0_src_core_factory_http_factory__["a" /* default */])(container.contextService);
}

/***/ }),
/* 274 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = HTTPClient;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_axios__ = __webpack_require__(275);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_axios___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_axios__);


function HTTPClient(context) {
    return createClient(context);
}

function createClient(context) {
    return __WEBPACK_IMPORTED_MODULE_0_axios___default.a.create({
        baseURL: context.apiPath
    });
}

/***/ }),
/* 275 */,
/* 276 */,
/* 277 */,
/* 278 */,
/* 279 */,
/* 280 */,
/* 281 */,
/* 282 */,
/* 283 */,
/* 284 */,
/* 285 */,
/* 286 */,
/* 287 */,
/* 288 */,
/* 289 */,
/* 290 */,
/* 291 */,
/* 292 */,
/* 293 */,
/* 294 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = initializeCoreModules;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module__ = __webpack_require__(295);



var ModuleFactory = Shopware.Module;

function initializeCoreModules() {
    __WEBPACK_IMPORTED_MODULE_0_module__["a" /* default */].forEach(function (module) {
        ModuleFactory.register(module, 'core');
    });

    return ModuleFactory.getRoutes();
}

/***/ }),
/* 295 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_product_manifest__ = __webpack_require__(296);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_module_core_order_manifest__ = __webpack_require__(318);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_module_core_login_manifest__ = __webpack_require__(334);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_module_core_dashboard_manifest__ = __webpack_require__(337);





/* harmony default export */ __webpack_exports__["a"] = ([__WEBPACK_IMPORTED_MODULE_2_module_core_login_manifest__["a" /* default */], __WEBPACK_IMPORTED_MODULE_3_module_core_dashboard_manifest__["a" /* default */], __WEBPACK_IMPORTED_MODULE_1_module_core_order_manifest__["a" /* default */], __WEBPACK_IMPORTED_MODULE_0_module_core_product_manifest__["a" /* default */]]);

/***/ }),
/* 296 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_product_src_components_page_core_product_list__ = __webpack_require__(297);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_module_core_product_src_components_page_core_product_detail__ = __webpack_require__(303);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_module_core_product_src_components_organism_core_product_sidebar__ = __webpack_require__(313);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_module_core_product_src_components__ = __webpack_require__(315);





/* harmony default export */ __webpack_exports__["a"] = ({
    id: 'core.product',
    name: 'Produkt Übersicht',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#7AD5C8',
    icon: 'box',

    routes: {
        index: {
            components: {
                default: __WEBPACK_IMPORTED_MODULE_0_module_core_product_src_components_page_core_product_list__["a" /* default */],
                sidebar: __WEBPACK_IMPORTED_MODULE_2_module_core_product_src_components_organism_core_product_sidebar__["a" /* default */]
            },
            path: 'index'
        },

        create: {
            component: __WEBPACK_IMPORTED_MODULE_1_module_core_product_src_components_page_core_product_detail__["a" /* default */],
            path: 'product/create',
            meta: {
                parentPath: 'core.product.index'
            }
        },

        detail: {
            component: __WEBPACK_IMPORTED_MODULE_1_module_core_product_src_components_page_core_product_detail__["a" /* default */],
            path: 'detail/:uuid',
            meta: {
                parentPath: 'core.product.index'
            }
        }
    },

    navigation: {
        root: [{
            'core.product.index': {
                icon: 'box',
                color: '#7AD5C8',
                name: 'Produktübersicht'
            }
        }]
    },

    commands: [{
        title: 'Übersicht',
        route: 'product.index'
    }, {
        title: '%0 öffnen',
        route: 'product.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'product.index.shortcut.mac',
                combination: ['CMD', 'P']
            },
            win: {
                title: 'product.index.shortcut.win',
                combination: ['CTRL', 'P']
            }
        }
    }
});

/***/ }),
/* 297 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__ = __webpack_require__(42);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_repository_product_list_repository__ = __webpack_require__(298);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__ = __webpack_require__(10);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_product_list_less__ = __webpack_require__(300);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_product_list_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3__core_product_list_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__core_product_list_twig__ = __webpack_require__(302);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__core_product_list_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4__core_product_list_twig__);






/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('core-product-list', {
    mixins: [__WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__["a" /* default */], __WEBPACK_IMPORTED_MODULE_1_src_core_repository_product_list_repository__["a" /* default */]],

    data: function data() {
        return {
            isWorking: true,
            productList: [],
            errors: []
        };
    },
    created: function created() {
        var _this = this;

        this.initProductList().then(function () {
            _this.isWorking = false;
        });
    },


    filters: {
        currency: __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].currency
    },

    methods: {
        onEdit: function onEdit(product) {
            if (product && product.uuid) {
                this.$router.push({ name: 'core.product.detail', params: { uuid: product.uuid } });
            }
        },
        handlePagination: function handlePagination(offset, limit) {
            var _this2 = this;

            this.isWorking = true;
            this.getProductList(offset, limit).then(function () {
                _this2.isWorking = false;
            });
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_4__core_product_list_twig___default.a
}));

/***/ }),
/* 298 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__factory_data_proxy_factory__ = __webpack_require__(96);


/* harmony default export */ __webpack_exports__["a"] = ({

    inject: ['productService'],

    getData: function getData() {
        return {
            offset: 0,
            limit: 25,
            total: 0
        };
    },


    methods: {
        initProductList: initProductList,
        getProductList: getProductList
    }
});

function initProductList() {
    var dataKey = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'productList';

    this.productListDataKey = dataKey;
    this[dataKey] = [];

    return this.getProductList(this.offset, this.limit);
}

function getProductList(offset, limit) {
    var _this = this;

    return this.productService.getList(offset, limit).then(function (response) {
        _this.productListProxy = __WEBPACK_IMPORTED_MODULE_0__factory_data_proxy_factory__["a" /* default */].create(response.data);
        _this[_this.productListDataKey] = _this.productListProxy.data;
        _this.total = response.total;
        _this.errors = response.errors;

        return {
            productListProxy: _this.productListProxy,
            total: response.total,
            errors: response.errors
        };
    });
}

/***/ }),
/* 299 */,
/* 300 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 301 */,
/* 302 */
/***/ (function(module, exports) {

module.exports = "{% block list %}\n    <sw-workspace>\n\n        <header class=\"toolbar--middle-section\" slot=\"header\">\n            <h1 class=\"workspace--headline\">\n                Produktübersicht\n            </h1>\n\n            <div class=\"toolbar--primary-actions\">\n                <router-link :to=\"{ name: 'core.product.create' }\">\n                    <sw-button :isPrimary=\"true\">Neues Produkt anlegen</sw-button>\n                </router-link>\n            </div>\n        </header>\n\n        <div class=\"core-product--list\">\n            {% block core_product_list_grid %}\n                <sw-grid :items=\"productList\" :pagination=\"true\" v-on:edit=\"onEdit\">\n                    {% block core_product_list_grid_slots %}\n\n                        <template slot=\"columns\" slot-scope=\"{ item }\">\n                            {% block core_product_list_grid_columns %}\n                                <sw-grid-column flex=\"50px\" label=\"#\"></sw-grid-column>\n                                <sw-grid-column flex=\"1\" label=\"Name\">{{ item.name }}</sw-grid-column>\n                                <sw-grid-column flex=\"1\" label=\"Manufacturer\">{{ item.manufacturer.name }}</sw-grid-column>\n                                <sw-grid-column flex=\"100px\" label=\"Active\"><i class=\"icon-checkmark\" v-if=\"item.active\"></i><i class=\"icon-cross\" v-else></i></sw-grid-column>\n                                <sw-grid-column flex=\"100px\" label=\"Price\">{{ item.listingPrices[0].price | currency }}</sw-grid-column>\n                                <sw-grid-column flex=\"200px\" label=\"Stock\">{{ item.stock }} in stock</sw-grid-column>\n                            {% endblock %}\n                        </template>\n\n                        <template slot=\"pagination\">\n                            {% block core_product_list_grid_pagination %}\n                                <sw-pagination :page=\"page\" :max-page=\"maxPage\" :limit=\"limit\" :total=\"total\" v-on:page-change=\"pageChange\"></sw-pagination>\n                            {% endblock %}\n                        </template>\n                    {% endblock %}\n                </sw-grid>\n            {% endblock %}\n\n            <sw-loader v-if=\"isWorking\"></sw-loader>\n        </div>\n    </sw-workspace>\n{% endblock %}";

/***/ }),
/* 303 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_core_repository_product_detail_repository__ = __webpack_require__(304);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_product_detail_html_twig__ = __webpack_require__(311);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_product_detail_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__core_product_detail_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_product_detail_less__ = __webpack_require__(312);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_product_detail_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2__core_product_detail_less__);




/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('core-product-detail', {
    inject: ['categoryService', 'productManufacturerService', 'taxService', 'customerGroupService'],

    mixins: [__WEBPACK_IMPORTED_MODULE_0_src_core_repository_product_detail_repository__["a" /* default */]],

    data: function data() {
        return {
            isWorking: false,
            product: {
                attribute: {},
                categories: [],
                prices: []
            },
            taxRates: [],
            manufacturers: [],
            customerGroups: []
        };
    },


    computed: {
        categoryService: function categoryService() {
            return this.categoryService;
        },
        customerGroupOptions: function customerGroupOptions() {
            var options = [];

            this.customerGroups.forEach(function (item) {
                options.push({
                    value: item.uuid,
                    label: item.name
                });
            });

            return options;
        },
        priceColumns: function priceColumns() {
            return [{ field: 'quantityStart', label: 'Von', type: 'number' }, { field: 'quantityEnd', label: 'Bis', type: 'number' }, { field: 'price', label: 'Preis', type: 'number' }, { field: 'pseudoPrice', label: 'Pseudo Preis', type: 'number' }, { field: 'customerGroupUuid', label: 'Kundengruppe', type: 'select', options: this.customerGroupOptions }];
        }
    },

    created: function created() {
        var _this = this;

        this.initProduct(this.$route.params.uuid).then(function (proxy) {
            _this.$emit('core-product-detail:load:after', proxy.data);
        });
        this.getData();
    },


    watch: {
        $route: 'getData'
    },

    methods: {
        getData: function getData() {
            this.getManufacturerData();
            this.getCustomerGroupData();
            this.getTaxData();
        },
        getManufacturerData: function getManufacturerData() {
            var _this2 = this;

            this.productManufacturerService.getList().then(function (response) {
                _this2.manufacturers = response.data;
            });
        },
        getTaxData: function getTaxData() {
            var _this3 = this;

            this.taxService.getList().then(function (response) {
                _this3.taxRates = response.data;
            });
        },
        getCustomerGroupData: function getCustomerGroupData() {
            var _this4 = this;

            this.customerGroupService.getList().then(function (response) {
                _this4.customerGroups = response.data;
            });
        },
        onSave: function onSave() {
            var _this5 = this;

            this.isWorking = true;

            this.$emit('core-product-detail:save:before', this);

            this.saveProduct().then(function (data) {
                _this5.isWorking = false;

                _this5.$emit('core-product-detail:save:after', data);

                if (!_this5.$route.params.uuid && data.uuid) {
                    _this5.$router.push({ path: '/core/product/detail/' + data.uuid });
                }
            }).catch(function () {
                _this5.isWorking = false;
            });
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_1__core_product_detail_html_twig___default.a
}));

/***/ }),
/* 304 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends__ = __webpack_require__(39);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(17);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise__ = __webpack_require__(43);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__factory_data_proxy_factory__ = __webpack_require__(96);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__service_util_service__ = __webpack_require__(10);






/* harmony default export */ __webpack_exports__["a"] = ({

    inject: ['productService'],

    methods: {
        initProduct: initProduct,
        saveProduct: saveProduct,
        getProductByUuid: getProductByUuid,
        updateProductByUuid: updateProductByUuid,
        createProduct: createProduct,
        getDefaultProduct: getDefaultProduct,
        getNewProduct: getNewProduct,
        addProductPrice: addProductPrice
    }
});

function initProduct(uuid) {
    var _this = this;

    var dataKey = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'product';

    this.productDataKey = dataKey;
    this[dataKey] = this.getDefaultProduct();

    if (!uuid) {
        var productProxy = this.getNewProduct();

        this.productProxy = productProxy;
        this[dataKey] = productProxy.data;

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise___default.a.resolve(function () {
            return productProxy;
        });
    }

    return this.getProductByUuid(uuid).then(function (productProxy) {
        _this.productProxy = productProxy;
        _this[dataKey] = productProxy.data;

        return productProxy;
    });
}

function saveProduct() {
    var _this2 = this;

    var uuid = this.productProxy.data.uuid;

    if (!uuid) {
        return this.createProduct(this.productProxy).then(function (data) {
            _this2.productProxy.data = data;
            return data;
        }).catch();
    }

    return this.updateProductByUuid(uuid, this.productProxy).then(function (data) {
        _this2.productProxy.data = data;
        return data;
    }).catch();
}

function getProductByUuid(uuid) {
    return this.productService.getByUuid(uuid).then(function (response) {
        return __WEBPACK_IMPORTED_MODULE_3__factory_data_proxy_factory__["a" /* default */].create(response.data);
    });
}

function updateProductByUuid(uuid, proxy) {
    if (!uuid || !proxy) {
        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise___default.a.reject(new Error('Missing required parameters.'));
    }

    if (__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default()(proxy.changeSet).length === 0) {
        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise___default.a.reject();
    }

    var changeSet = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends___default()({}, proxy.changeSet);

    if (changeSet.categories) {
        changeSet.categories = mapCategories(changeSet.categories);
    }

    return this.productService.updateByUuid(uuid, changeSet).then(function (response) {
        return response.data;
    });
}

function createProduct(proxy) {
    var data = proxy.data;

    if (data.categories) {
        data.categories = mapCategories(data.categories);
    }

    return this.productService.create([proxy.data]).then(function (response) {
        if (response.errors.length) {
            return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_promise___default.a.reject(new Error('API error'));
        }

        return response.data[0];
    });
}

function getDefaultProduct() {
    return {
        attribute: {},
        categories: []
    };
}

function getNewProduct() {
    var product = {
        uuid: null,
        taxUuid: 'SWAG-TAX-UUID-1',
        isMain: true,
        manufacturerUuid: null,
        prices: [{
            uuid: null,
            price: 0,
            basePrice: 0,
            pseudoPrice: null,
            quantityStart: 1,
            quantityEnd: null,
            percentage: 0,
            customerGroupUuid: '3294e6f6-372b-415f-ac73-71cbc191548f'
        }]
    };

    return __WEBPACK_IMPORTED_MODULE_3__factory_data_proxy_factory__["a" /* default */].create(product);
}

function addProductPrice() {
    var uuid = __WEBPACK_IMPORTED_MODULE_4__service_util_service__["default"].createUuid();

    this[this.productDataKey].prices.push({
        uuid: uuid,
        price: 0,
        basePrice: 0,
        pseudoPrice: null,
        quantityStart: 1,
        quantityEnd: null,
        percentage: null,
        customerGroupUuid: '3294e6f6-372b-415f-ac73-71cbc191548f'
    });
}

function mapCategories(categories) {
    var mappedCategories = [];

    categories.forEach(function (entry) {
        mappedCategories.push({
            categoryUuid: entry.uuid
        });
    });

    return mappedCategories;
}

/***/ }),
/* 305 */,
/* 306 */,
/* 307 */,
/* 308 */,
/* 309 */,
/* 310 */,
/* 311 */
/***/ (function(module, exports) {

module.exports = "<sw-workspace>\n    <header class=\"toolbar--middle-section\" slot=\"header\">\n        <h1 class=\"workspace--headline\">\n            <span v-if=\"product.name\">{{ product.name }} <span v-if=\"product.manufacturer\" class=\"is--small\">von {{ product.manufacturer.name }}</span></span>\n            <span v-else class=\"headline--empty\">(neues Produkt)</span>\n        </h1>\n\n        <div class=\"toolbar--primary-actions\">\n            <sw-button :isDisabled=\"isWorking\" link=\"core.product.index\">\n                Abbrechen\n            </sw-button>\n\n            <sw-button :isPrimary=\"true\" :isDisabled=\"isWorking\" @click.prevent=\"onSave\">\n                Speichern\n            </sw-button>\n        </div>\n    </header>\n\n    <div class=\"sw-core-product--detail\" slot=\"default\">\n        <ul class=\"container--tabs\">\n            <li class=\"tabs--item router-link-active\">\n                <a href=\"#\" class=\"item--link\">\n                    Allgemein\n                </a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">\n                    Cross Selling\n                </a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">\n                    Tab\n                </a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">\n                    Tab\n                </a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">\n                    Tab\n                </a>\n            </li>\n        </ul>\n\n        <core-product-basic-form :product=\"product\" :manufacturers=\"manufacturers\" :isWorking=\"isWorking\" :serviceProvider=\"categoryService\"></core-product-basic-form>\n\n        {% block core_product_detail_workspace_additional %}{% endblock %}\n\n        <sw-card title=\"Preise\">\n            <sw-form-grid :columns=\"priceColumns\" :items=\"product.prices\"></sw-form-grid>\n            <sw-loader v-if=\"isWorking\"></sw-loader>\n        </sw-card>\n    </div>\n</sw-workspace>\n";

/***/ }),
/* 312 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 313 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_product_sidebar_html_twig__ = __webpack_require__(314);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_product_sidebar_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__core_product_sidebar_html_twig__);


/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('core-product-sidebar', {
    template: __WEBPACK_IMPORTED_MODULE_0__core_product_sidebar_html_twig___default.a
}));

/***/ }),
/* 314 */
/***/ (function(module, exports) {

module.exports = "<div class=\"core-product--sidebar\">\n    <h2>Sidebar content</h2>\n    <p>\n        Lorem ipsum dolor sit amet, consectetur adipisicing elit. At blanditiis cumque doloremque ducimus eum laboriosam modi nisi, perspiciatis recusandae reiciendis? Aliquid illo iusto laboriosam, nisi porro saepe tempora! Rerum, velit?\n    </p>\n</div>";

/***/ }),
/* 315 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_product_src_components_molecule_core_product_basic_form__ = __webpack_require__(316);


/***/ }),
/* 316 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_product_basic_form_html_twig__ = __webpack_require__(317);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_product_basic_form_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__core_product_basic_form_html_twig__);


/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('core-product-basic-form', {
    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturers: {
            type: Array,
            required: true,
            default: []
        },
        isWorking: {
            type: Boolean,
            required: true,
            default: false
        },
        serviceProvider: {
            type: Object,
            required: true
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0__core_product_basic_form_html_twig___default.a
}));

/***/ }),
/* 317 */
/***/ (function(module, exports) {

module.exports = "<sw-card title=\"Produkt Informationen\" description=\"Allgemeine Informationen zum Produkt. Lorem ipsum dolor sit amet, consectetur adipisicing elit. A dolor praesentium repellat sequi tempora. Facilis minima, quibusdam. Assumenda atque commodi dicta, eum excepturi nostrum optio quis quos reiciendis vel voluptatem.\">\n\n    <sw-field label=\"Titel\" id=\"title\" type=\"text\" name=\"name\" placeholder=\"Your product title...\" v-model=\"product.name\"></sw-field>\n\n    <sw-field label=\"Artikel-Beschreibung\" id=\"descriptionLong\" type=\"textarea\" name=\"descriptionLong\" placeholder=\"Your product description...\" v-model=\"product.descriptionLong\"></sw-field>\n\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Artikelnummer\" id=\"uuid\" type=\"text\" name=\"uuid\" placeholder=\"Your product Uuid\" :value=\"product.uuid\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Hersteller\" id=\"manufacturer\" type=\"select\" name=\"manufacturer\" placeholder=\"Your product manufacturer\" v-model=\"product.manufacturerUuid\">\n                <option v-for=\"manufacturer in manufacturers\" :value=\"manufacturer.uuid\">{{ manufacturer.name }}</option>\n            </sw-field>\n        </div>\n    </div>\n\n    <sw-multi-select label=\"Kategorie\" id=\"category\" placeholder=\"Your product categories...\" :serviceProvider=\"serviceProvider\" :values=\"product.categories\" v-model=\"product.categories\"></sw-multi-select>\n    <sw-loader v-if=\"isWorking\"></sw-loader>\n</sw-card>\n";

/***/ }),
/* 318 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_order_src_components_page_core_order_list__ = __webpack_require__(319);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_module_core_order_src_components_page_core_order_detail__ = __webpack_require__(322);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_module_core_order_src_components__ = __webpack_require__(325);




/* harmony default export */ __webpack_exports__["a"] = ({
    id: 'core.order',
    name: 'Bestellübersicht',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#CA8EE0',
    icon: 'cart',

    routes: {
        index: {
            components: {
                default: __WEBPACK_IMPORTED_MODULE_0_module_core_order_src_components_page_core_order_list__["a" /* default */]
            },
            path: 'index'
        },
        detail: {
            component: __WEBPACK_IMPORTED_MODULE_1_module_core_order_src_components_page_core_order_detail__["a" /* default */],
            path: 'detail/:uuid',
            meta: {
                parentPath: 'core.order.index'
            }
        }
    },

    navigation: {
        root: [{
            'core.order.index': {
                icon: 'cart',
                color: '#CA8EE0',
                name: 'Bestellübersicht'
            }
        }]
    },

    commands: [{
        title: 'Übersicht',
        route: 'order.index'
    }, {
        title: '%0 öffnen',
        route: 'order.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'order.index.shortcut.mac',
                combination: ['CMD', 'O']
            },
            win: {
                title: 'order.index.shortcut.win',
                combination: ['CTRL', 'O']
            }
        }
    }
});

/***/ }),
/* 319 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__ = __webpack_require__(42);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__ = __webpack_require__(10);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_order_list_less__ = __webpack_require__(320);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_order_list_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2__core_order_list_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_order_list_twig__ = __webpack_require__(321);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_order_list_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3__core_order_list_twig__);





/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('core-order-list', {
    inject: ['orderService'],
    mixins: [__WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__["a" /* default */]],

    data: function data() {
        return {
            isWorking: false,
            orderList: [],
            errors: []
        };
    },
    created: function created() {
        this.getData();
    },


    filters: {
        currency: __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].currency,
        date: __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].date
    },

    methods: {
        getData: function getData() {
            var _this = this;

            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this.offset;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this.limit;

            this.isWorking = true;
            this.orderService.getList(offset, limit).then(function (response) {
                _this.orderList = response.data;
                _this.errors = response.errors;
                _this.total = response.total;
                _this.isWorking = false;
            });
        },
        onEdit: function onEdit(order) {
            if (order && order.uuid) {
                this.$router.push({ name: 'core.order.detail', params: { uuid: order.uuid } });
            }
        },
        handlePagination: function handlePagination(offset, limit) {
            this.getData(offset, limit);
        }
    },
    template: __WEBPACK_IMPORTED_MODULE_3__core_order_list_twig___default.a
}));

/***/ }),
/* 320 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 321 */
/***/ (function(module, exports) {

module.exports = "{% block list %}\n    <sw-workspace>\n\n        <header class=\"toolbar--middle-section\" slot=\"header\">\n            <h1 class=\"workspace--headline\">Bestellübersicht</h1>\n            <div class=\"toolbar--primary-actions\"></div>\n        </header>\n\n        <div class=\"core-order--list\">\n            {% block core_order_list_grid %}\n                <sw-grid :items=\"orderList\" :pagination=\"true\" v-on:edit=\"onEdit\">\n                    {% block core_order_list_grid_slots %}\n\n                    <template slot=\"columns\" slot-scope=\"{ item }\">\n                        {% block core_order_list_grid_columns %}\n                            <sw-grid-column flex=\"1\" label=\"Bestelldatum\">{{ item.date.date | moment('lll') }}</sw-grid-column>\n                            <sw-grid-column flex=\"2\" label=\"Kunde\">\n                                {{ item.customer.firstName }}<br>\n                                {{ item.customer.lastName }}<br>\n                                {{ item.customer.email }}\n                            </sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Zahlungsart\">{{ item.paymentMethod.name }}</sw-grid-column>\n                            <sw-grid-column flex=\"2\" label=\"Rechn.-Adresse\">\n                                {{ item.billingAddress.street }}<br>\n                                {{ item.billingAddress.zipcode }} {{ item.billingAddress.city }}<br>\n                                {{ item.billingAddress.country.name }}\n                            </sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Status\">{{ item.state.description }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Währung\">{{ item.currency.name }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Shop\">{{ item.shop.name }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Warenkorbwert\">{{ item.positionPrice | currency(item.currency.shortName) }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Gesamtbetrag\">{{ item.amountTotal | currency(item.currency.shortName) }}</sw-grid-column>\n                        {% endblock %}\n                    </template>\n\n                    <template slot=\"pagination\">\n                        {% block core_order_list_grid_pagination %}\n                            <sw-pagination :page=\"page\" :max-page=\"maxPage\" :limit=\"limit\" :total=\"total\" v-on:page-change=\"pageChange\"></sw-pagination>\n                        {% endblock %}\n                    </template>\n                    {% endblock %}\n                </sw-grid>\n            {% endblock %}\n\n            <sw-loader v-if=\"isWorking\"></sw-loader>\n        </div>\n    </sw-workspace>\n{% endblock %}";

/***/ }),
/* 322 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends__ = __webpack_require__(39);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__ = __webpack_require__(10);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_order_detail_html_twig__ = __webpack_require__(323);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__core_order_detail_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2__core_order_detail_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_order_detail_less__ = __webpack_require__(324);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__core_order_detail_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3__core_order_detail_less__);





/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('core-order-detail', {
    inject: ['orderService', 'customerService', 'shopService', 'orderStateService', 'currencyService', 'countryService', 'paymentMethodService', 'countryService'],

    data: function data() {
        return {
            isWorking: false,
            order: {
                customer: {},
                currency: {},
                billingAddress: {},
                paymentMethod: {},
                lineItems: [],
                deliveries: [],

                state: {}
            },
            customers: [],
            countries: [],
            shops: [],
            currencies: [],
            orderStates: [],
            paymentMethods: [],
            notModifiedOrder: {}
        };
    },
    created: function created() {
        this.getData();
    },


    watch: {
        $route: 'getData'
    },

    methods: {
        getData: function getData() {
            this.getOrderData();
            this.getCustomerData();
            this.getPaymentMethodData();
            this.getCountryData();
            this.getShopData();
            this.getCurrencyData();
            this.getOrderStateData();
        },
        getOrderData: function getOrderData() {
            var _this = this;

            var uuid = this.$route.params.uuid;

            this.isWorking = true;
            this.orderService.getByUuid(uuid).then(function (response) {
                _this.notModifiedOrder = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends___default()({}, response.data);
                _this.order = response.data;
                _this.isWorking = false;
            });
        },
        getCustomerData: function getCustomerData() {
            var _this2 = this;

            this.customerService.getList().then(function (response) {
                _this2.customers = response.data;
            });
        },
        getPaymentMethodData: function getPaymentMethodData() {
            var _this3 = this;

            this.paymentMethodService.getList().then(function (response) {
                _this3.paymentMethods = response.data;
            });
        },
        getCountryData: function getCountryData() {
            var _this4 = this;

            this.countryService.getList().then(function (response) {
                _this4.countries = response.data;
            });
        },
        getShopData: function getShopData() {
            var _this5 = this;

            this.shopService.getList().then(function (response) {
                _this5.shops = response.data;
            });
        },
        getCurrencyData: function getCurrencyData() {
            var _this6 = this;

            this.currencyService.getList().then(function (response) {
                _this6.currencies = response.data;
            });
        },
        getOrderStateData: function getOrderStateData() {
            var _this7 = this;

            this.orderStateService.getList().then(function (response) {
                _this7.orderStates = response.data;
            });
        },
        onSaveForm: function onSaveForm() {
            var _this8 = this;

            var uuid = this.$route.params.uuid;
            var changeSet = __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].getObjectChangeSet(this.notModifiedOrder, this.order);

            this.isWorking = true;
            this.orderService.updateByUuid(uuid, changeSet).then(function (response) {
                _this8.notModifiedOrder = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_extends___default()({}, response.data);
                _this8.order = response.data;
                _this8.isWorking = false;
            });
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_2__core_order_detail_html_twig___default.a
}));

/***/ }),
/* 323 */
/***/ (function(module, exports) {

module.exports = "<sw-workspace>\n    <header class=\"toolbar--middle-section\" slot=\"header\">\n        <h1 class=\"workspace--headline\">\n            <span v-if=\"!isWorking\">{{ order.uuid }} <span class=\"is--small\">von {{ order.customer.firstName }} {{ order.customer.lastName }}</span></span>\n            <span v-else class=\"headline--empty\">(lade Bestellung...)</span>\n        </h1>\n\n        <div class=\"toolbar--primary-actions\">\n            <sw-button :isDisabled=\"isWorking\" link=\"core.order.index\">Abbrechen</sw-button>\n\n            <sw-button :isPrimary=\"true\" :isDisabled=\"isWorking\" @click.prevent=\"onSaveForm\">Speichern</sw-button>\n        </div>\n    </header>\n\n    <div class=\"sw-core-order--detail\" slot=\"default\">\n        <ul class=\"container--tabs\">\n            <li class=\"tabs--item router-link-active\">\n                <a href=\"#\" class=\"item--link\">Allgemein</a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">Positionen</a>\n            </li>\n            <li class=\"tabs--item\">\n                <a href=\"#\" class=\"item--link\">Lieferungen</a>\n            </li>\n        </ul>\n\n        <core-order-basic-form\n                :isWorking=\"isWorking\"\n                :order=\"order\"\n                :shops=\"shops\"\n                :currencies=\"currencies\"\n                :orderStates=\"orderStates\"\n                :customers=\"customers\"\n                :paymentMethods=\"paymentMethods\" >\n\n        </core-order-basic-form>\n\n        <sw-card title=\"Beträge\">\n            <div class=\"container--item\">\n                <sw-field label=\"Total amount\" id=\"amountTotal\" type=\"string\" name=\"amountTotal\" v-model=\"order.amountTotal\" :suffix=\"order.currency.shortName\"></sw-field>\n            </div>\n            <div class=\"container--item\">\n                <sw-field label=\"Position price\" id=\"positionPrice\" type=\"string\" name=\"positionPrice\" v-model=\"order.positionPrice\" :suffix=\"order.currency.shortName\"></sw-field>\n            </div>\n            <div class=\"container--item\">\n                <sw-field label=\"Total shipping costs\" id=\"shippingTotal\" type=\"string\" name=\"shippingTotal\" v-model=\"order.shippingTotal\" :suffix=\"order.currency.shortName\"></sw-field>\n            </div>\n            <div class=\"container--item\">\n                <sw-field label=\"Is tax free\" id=\"isTaxFree\" type=\"checkbox\" name=\"isTaxFree\" v-model=\"order.isTaxFree\"></sw-field>\n            </div>\n            <div class=\"container--item\">\n                <sw-field label=\"Net prices\" id=\"isNet\" type=\"checkbox\" name=\"isNet\" v-model=\"order.isNet\"></sw-field>\n            </div>\n        </sw-card>\n\n        <sw-card title=\"Rechnungsadresse\">\n            <core-order-address-form :address=\"order.billingAddress\" :isWorking=\"isWorking\" :countries=\"countries\" ></core-order-address-form>\n        </sw-card>\n\n        <sw-card title=\"Positionen\">\n            <core-order-line-item-list :order=\"order\"></core-order-line-item-list>\n            <sw-loader v-if=\"isWorking\"></sw-loader>\n        </sw-card>\n\n        <sw-card title=\"Lieferungen\">\n            <core-order-delivery-list :order=\"order\"></core-order-delivery-list>\n            <sw-loader v-if=\"isWorking\"></sw-loader>\n        </sw-card>\n    </div>\n</sw-workspace>\n";

/***/ }),
/* 324 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 325 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_order_src_components_molecule_core_order_basic_form__ = __webpack_require__(326);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_module_core_order_src_components_molecule_core_order_address_form__ = __webpack_require__(328);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_module_core_order_src_components_molecule_core_order_line_item_list__ = __webpack_require__(330);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_module_core_order_src_components_molecule_core_order_delivery_list__ = __webpack_require__(332);





/***/ }),
/* 326 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_order_basic_form_html_twig__ = __webpack_require__(327);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_order_basic_form_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__core_order_basic_form_html_twig__);


/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('core-order-basic-form', {
    props: {
        order: {
            type: Object,
            required: true,
            default: {}
        },
        customers: {
            type: Array,
            required: true,
            default: []
        },
        shops: {
            type: Array,
            required: true,
            default: []
        },
        currencies: {
            type: Array,
            required: true,
            default: []
        },
        orderStates: {
            type: Array,
            required: true,
            default: []
        },
        paymentMethods: {
            type: Array,
            required: true,
            default: []
        },
        isWorking: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0__core_order_basic_form_html_twig___default.a
}));

/***/ }),
/* 327 */
/***/ (function(module, exports) {

module.exports = "<sw-card title=\"Bestellungsinformationen\" description=\"Bestellungs-Informationen für Bestellung\">\n\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Customer\" id=\"customer\" type=\"select\" name=\"customer\" placeholder=\"The customer\" v-model=\"order.customerUuid\">\n                <option v-for=\"customer in customers\" :value=\"customer.uuid\">{{ customer.firstName }} {{ customer.lastName }}</option>\n            </sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Shop\" id=\"shopUuid\" type=\"select\" name=\"shopUuid\" v-model=\"order.shopUuid\">\n                <option v-for=\"shop in shops\" :value=\"shop.uuid\">{{ shop.name }}</option>\n            </sw-field>\n        </div>\n    </div>\n\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Order date\" id=\"orderDate\" type=\"datetime\" name=\"orderDate\" v-model=\"order.date\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Order state\" id=\"stateUuid\" type=\"select\" name=\"stateUuid\" v-model=\"order.stateUuid\">\n                <option v-for=\"orderState in orderStates\" :value=\"orderState.uuid\">{{ orderState.description }}</option>\n            </sw-field>\n        </div>\n    </div>\n\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Payment method\" id=\"paymentMethod\" type=\"select\" name=\"paymentMethod\" placeholder=\"The payment method\" v-model=\"order.paymentMethodUuid\">\n                <option v-for=\"paymentMethod in paymentMethods\" :value=\"paymentMethod.uuid\">{{ paymentMethod.name }}</option>\n            </sw-field>\n        </div>\n\n        <div class=\"container--item\">\n            <sw-field label=\"Currency\" id=\"currencyUuid\" type=\"select\" name=\"currencyUuid\" v-model=\"order.currencyUuid\">\n                <option v-for=\"currency in currencies\" :value=\"currency.uuid\">{{ currency.name }}</option>\n            </sw-field>\n        </div>\n    </div>\n\n    <sw-loader v-if=\"isWorking\"></sw-loader>\n</sw-card>\n";

/***/ }),
/* 328 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_order_address_form_html_twig__ = __webpack_require__(329);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__core_order_address_form_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__core_order_address_form_html_twig__);


/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('core-order-address-form', {
    props: {
        address: {
            type: Object,
            required: true,
            default: {}
        },
        countries: {
            type: Array,
            required: true,
            default: []
        },
        isWorking: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0__core_order_address_form_html_twig___default.a
}));

/***/ }),
/* 329 */
/***/ (function(module, exports) {

module.exports = "<div class=\"address-form\">\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Company\" id=\"company\" type=\"string\" name=\"company\" v-model=\"address.company\" placeholder=\"The Company of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Department\" id=\"department\" type=\"string\" name=\"department\" v-model=\"address.department\" placeholder=\"The Department of the address\"></sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Salutation\" id=\"salutation\" type=\"string\" name=\"salutation\" v-model=\"address.salutation\" placeholder=\"The Salutation of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Title\" id=\"title\" type=\"string\" name=\"title\" v-model=\"address.title\" placeholder=\"The Title of the address\"></sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"First name\" id=\"firstName\" type=\"string\" name=\"firstName\" v-model=\"address.firstName\" placeholder=\"The First_name of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Last name\" id=\"lastName\" type=\"string\" name=\"lastName\" v-model=\"address.lastName\" placeholder=\"The Last_name of the address\"></sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Street\" id=\"street\" type=\"string\" name=\"street\" v-model=\"address.street\" placeholder=\"The Street of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Zipcode\" id=\"zipcode\" type=\"string\" name=\"zipcode\" v-model=\"address.zipcode\" placeholder=\"The Zipcode of the address\"></sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"City\" id=\"city\" type=\"string\" name=\"city\" v-model=\"address.city\" placeholder=\"The City of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Area country\" id=\"areaCountryUuid\" type=\"select\" name=\"country\" placeholder=\"The country\" v-model=\"address.areaCountryUuid\">\n                <option v-for=\"country in countries\" :value=\"country.uuid\">{{ country.name }}</option>\n            </sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Vat id\" id=\"vatId\" type=\"string\" name=\"vatId\" v-model=\"address.vatId\" placeholder=\"The Vat_id of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Phone number\" id=\"phoneNumber\" type=\"string\" name=\"phoneNumber\" v-model=\"address.phoneNumber\" placeholder=\"The Phone_number of the address\"></sw-field>\n        </div>\n    </div>\n    <div class=\"split--container\">\n        <div class=\"container--item\">\n            <sw-field label=\"Additional address line1\" id=\"additionalAddressLine1\" type=\"string\" name=\"additionalAddressLine1\" v-model=\"address.additionalAddressLine1\" placeholder=\"The Additional_address_line1 of the address\"></sw-field>\n        </div>\n        <div class=\"container--item\">\n            <sw-field label=\"Additional address line2\" id=\"additionalAddressLine2\" type=\"string\" name=\"additionalAddressLine2\" v-model=\"address.additionalAddressLine2\" placeholder=\"The Additional_address_line2 of the address\"></sw-field>\n        </div>\n    </div>\n    <sw-loader v-if=\"isWorking\"></sw-loader>\n</div>";

/***/ }),
/* 330 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__ = __webpack_require__(42);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_order_line_item_list_html_twig__ = __webpack_require__(331);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_order_line_item_list_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__core_order_line_item_list_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('core-order-line-item-list', {
    inject: ['orderLineItemService'],
    mixins: [__WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__["a" /* default */]],

    props: {
        order: {
            type: Object,
            required: true
        }
    },

    data: function data() {
        return {
            isWorking: false,
            lineItems: [],
            errors: []
        };
    },


    watch: {
        order: 'getData'
    },

    methods: {
        getData: function getData() {
            this.getOrderItemsList();
        },
        getOrderItemsList: function getOrderItemsList() {
            var _this = this;

            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this.offset;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this.limit;

            this.isWorking = true;
            this.orderLineItemService.getList(offset, limit, this.order.uuid).then(function (response) {
                _this.lineItems = response.data;
                _this.errors = response.errors;
                _this.total = response.total;
                _this.isWorking = false;
            });
        },
        handlePagination: function handlePagination(offset, limit) {
            this.getOrderItemsList(offset, limit);
        }
    },
    template: __WEBPACK_IMPORTED_MODULE_1__core_order_line_item_list_html_twig___default.a
}));

/***/ }),
/* 331 */
/***/ (function(module, exports) {

module.exports = "{% block order_line_item_list %}\n\n    <div class=\"core-order-line-item--list\">\n        {% block order_line_item_list_grid %}\n            <sw-grid :items=\"lineItems\" :pagination=\"true\" :actions=\"[]\" v-if=\"lineItems.length > 0\">\n                {% block order_line_item_list_grid_slots %}\n\n                    <template slot=\"columns\" slot-scope=\"{ item }\">\n                        {% block order_line_lineItem_list_grid_columns %}\n                            <sw-grid-column flex=\"1\" label=\"Type\">{{ item.type }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Stückpreis\">{{ item.unitPrice | currency(order.currency.shortName) }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Anzahl\">{{ item.quantity }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Gesamtpreis\">{{ item.totalPrice | currency(order.currency.shortName) }}</sw-grid-column>\n                        {% endblock %}\n                    </template>\n\n                    <template slot=\"pagination\">\n                        {% block order_line_item_list_grid_pagination %}\n                            <sw-pagination :page=\"page\" :max-page=\"maxPage\" :limit=\"limit\" :total=\"total\" v-on:page-change=\"pageChange\"></sw-pagination>\n                        {% endblock %}\n                    </template>\n                {% endblock %}\n            </sw-grid>\n        {% endblock %}\n\n        <sw-loader v-if=\"isWorking\"></sw-loader>\n    </div>\n{% endblock %}";

/***/ }),
/* 332 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__ = __webpack_require__(42);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_order_delivery_list_html_twig__ = __webpack_require__(333);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__core_order_delivery_list_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__core_order_delivery_list_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('core-order-delivery-list', {
    inject: ['orderDeliveryService'],
    mixins: [__WEBPACK_IMPORTED_MODULE_0_src_app_component_mixin_pagination_mixin__["a" /* default */]],

    props: {
        order: {
            type: Object,
            required: true
        }
    },

    data: function data() {
        return {
            isWorking: false,
            deliveryList: [],
            errors: []
        };
    },


    watch: {
        order: 'getData'
    },

    methods: {
        getData: function getData() {
            this.getDeliveryList();
        },
        getDeliveryList: function getDeliveryList() {
            var _this = this;

            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this.offset;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this.limit;

            this.isWorking = true;
            this.orderDeliveryService.getList(offset, limit, this.order.uuid).then(function (response) {
                _this.deliveryList = response.data;
                _this.errors = response.errors;
                _this.total = response.total;
                _this.isWorking = false;
            });
        },
        handlePagination: function handlePagination(offset, limit) {
            this.getDeliveryList(offset, limit);
        }
    },
    template: __WEBPACK_IMPORTED_MODULE_1__core_order_delivery_list_html_twig___default.a
}));

/***/ }),
/* 333 */
/***/ (function(module, exports) {

module.exports = "{% block order_delivery_list %}\n\n    <div class=\"core-order-delivery--list\">\n        {% block order_delivery_list_grid %}\n            <sw-grid :items=\"deliveryList\" :pagination=\"true\" :actions=\"[]\">\n                {% block order_delivery_list_grid_slots %}\n\n                    <template slot=\"columns\" slot-scope=\"{ item }\">\n                        {% block order_delivery_list_grid_columns %}\n                            <sw-grid-column flex=\"2\" label=\"Lieferadresse\">\n                                {{ item.shippingAddress.street }}<br>\n                                {{ item.shippingAddress.zipcode }} {{ item.shippingAddress.city }}<br>\n                                {{ item.shippingAddress.country.name }}\n                            </sw-grid-column>\n                            <sw-grid-column flex=\"2\" label=\"Versandart\">{{ item.shippingMethod.name}}</sw-grid-column>\n                            <sw-grid-column flex=\"2\" label=\"Lieferdatum\">\n                                {{ item.shippingDateEarliest.date | moment('lll') }}\n                                <br> bis <br>\n                                {{ item.shippingDateLatest.date | moment('lll') }}\n                            </sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Lieferstatus\">{{ item.state.description }}</sw-grid-column>\n                            <sw-grid-column flex=\"1\" label=\"Trackingcode\">{{ item.tackingCode }}</sw-grid-column>\n                        {% endblock %}\n                    </template>\n\n                    <template slot=\"pagination\">\n                        {% block order_delivery_list_grid_pagination %}\n                            <sw-pagination :page=\"page\" :max-page=\"maxPage\" :limit=\"limit\" :total=\"total\" v-on:page-change=\"pageChange\"></sw-pagination>\n                        {% endblock %}\n                    </template>\n                {% endblock %}\n            </sw-grid>\n        {% endblock %}\n\n        <sw-loader v-if=\"isWorking\"></sw-loader>\n    </div>\n\n{% endblock %}";

/***/ }),
/* 334 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login__ = __webpack_require__(335);


/* harmony default export */ __webpack_exports__["a"] = ({
    id: 'core.login',
    name: 'Core Login Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    routes: {
        index: {
            component: __WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login__["a" /* default */],
            path: 'index',
            alias: 'signin'
        }
    },

    navigation: {
        root: [{
            'core.login.index': {
                icon: 'enter',
                color: '#F19D12',
                name: 'Login'
            }
        }]
    }
});

/***/ }),
/* 335 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login_html_twig__ = __webpack_require__(336);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__ = __webpack_require__(10);



/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('sw-login', {

    template: __WEBPACK_IMPORTED_MODULE_0_module_core_login_src_view_sw_login_sw_login_html_twig___default.a,

    inject: ['loginService', 'applicationState'],

    data: function data() {
        return {
            error: '',
            message: ''
        };
    },


    computed: {
        state: function state() {
            return this.applicationState.mapState(['user']);
        }
    },

    methods: {
        onHideErrorMessage: function onHideErrorMessage() {
            this.error = '';
            this.message = '';
        },
        onLogin: function onLogin() {
            var _this = this;

            var formData = new FormData(this.$refs.form.$el.querySelector('form'));
            var data = __WEBPACK_IMPORTED_MODULE_1_src_core_service_util_service__["default"].formDataToObject(formData);

            this.error = '';
            this.message = '';

            this.loginService.loginByUsername(data.username, data.password).then(function (response) {
                response = response.data;

                if (!response.success) {
                    _this.error = response.error;
                    _this.message = response.message;
                    return false;
                }

                _this.applicationState.commit('setUser', response.user);
                _this.applicationState.commit('setToken', response.token);

                _this.$router.push({ path: '/' });
                return true;
            }).catch(function (err) {
                _this.error = err.message;
            });
        }
    }
}));

/***/ }),
/* 336 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-login\">\n    <h1>Administration Login</h1>\n\n    <transition name=\"bounce\">\n        <div class=\"alert error\" v-if=\"error\">\n            <p>\n                <strong>{{error}}!</strong> {{message}}\n            </p>\n            <i class=\"icon--close\" @click=\"onHideErrorMessage\">&times;</i>\n        </div>\n    </transition>\n\n    <div v-if=\"state.user.username\">\n        <strong>Logged in user:</strong> {{state.user.name}}\n    </div>\n\n    <sw-form action=\"post\" ref=\"form\">\n        <sw-form-row>\n            <sw-form-field id=\"username\" name=\"username\" label=\"Username\" placeholder=\"Your username\"></sw-form-field>\n        </sw-form-row>\n        <sw-form-row>\n            <sw-form-field id=\"password\" name=\"password\" label=\"Password\" type=\"password\" placeholder=\"Your password\"></sw-form-field>\n        </sw-form-row>\n\n        <button @click=\"onLogin\">Login!</button>\n    </sw-form>\n</div>";

/***/ }),
/* 337 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard__ = __webpack_require__(338);


/* harmony default export */ __webpack_exports__["a"] = ({
    id: 'core.dashboard',
    name: 'Core Dashboard Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6abff0',

    routes: {
        index: {
            component: __WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard__["a" /* default */],
            path: 'index'
        }
    },

    navigation: {
        root: [{
            'core.dashboard.index': {
                icon: 'browser',
                color: '#6abff0',
                name: 'Dashboard'
            }
        }]
    }
});

/***/ }),
/* 338 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__babel_loader_node_modules_vue_loader_lib_selector_type_script_index_0_sw_dashboard_vue__ = __webpack_require__(340);
var normalizeComponent = __webpack_require__(339)
/* script */

/* template */
var __vue_template__ = null
/* template functional */
var __vue_template_functional__ = false
/* styles */
var __vue_styles__ = null
/* scopeId */
var __vue_scopeId__ = null
/* moduleIdentifier (server only) */
var __vue_module_identifier__ = null
var Component = normalizeComponent(
  __WEBPACK_IMPORTED_MODULE_0__babel_loader_node_modules_vue_loader_lib_selector_type_script_index_0_sw_dashboard_vue__["a" /* default */],
  __vue_template__,
  __vue_template_functional__,
  __vue_styles__,
  __vue_scopeId__,
  __vue_module_identifier__
)

/* harmony default export */ __webpack_exports__["a"] = (Component.exports);


/***/ }),
/* 339 */,
/* 340 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard_html__ = __webpack_require__(341);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard_html___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard_html__);



/* harmony default export */ __webpack_exports__["a"] = (Shopware.Component.register('sw-dashboard', {
    template: __WEBPACK_IMPORTED_MODULE_0_module_core_dashboard_src_sw_dashboard_html___default.a
}));

/***/ }),
/* 341 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-dashboard\">\n    <h1>Dashboard</h1>\n</div>";

/***/ }),
/* 342 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = initializeView;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_adapter_view_vue_adapter__ = __webpack_require__(343);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_factory_view_factory__ = __webpack_require__(393);





function initializeView(container) {
  var adapter = Object(__WEBPACK_IMPORTED_MODULE_0_src_app_adapter_view_vue_adapter__["a" /* default */])(container.contextService);
  var viewFactory = Object(__WEBPACK_IMPORTED_MODULE_1_src_core_factory_view_factory__["a" /* default */])(adapter);

  viewFactory.initComponents();
  return viewFactory;
}

/***/ }),
/* 343 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = VueAdapter;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_components__ = __webpack_require__(344);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_vue__ = __webpack_require__(388);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_vue_router__ = __webpack_require__(101);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_src_core_service_util_service__ = __webpack_require__(10);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_vue_moment__ = __webpack_require__(391);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_vue_moment___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_vue_moment__);







var vueComponents = {};

function VueAdapter(context) {
    __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].use(__WEBPACK_IMPORTED_MODULE_2_vue_router__["a" /* default */]);
    __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].use(__WEBPACK_IMPORTED_MODULE_4_vue_moment___default.a);

    __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].filter('image', function (value) {
        if (!value) {
            return '';
        }

        return '' + context.assetsPath + value;
    });

    __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].filter('currency', function (value) {
        var format = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'EUR';

        return __WEBPACK_IMPORTED_MODULE_3_src_core_service_util_service__["default"].currency(value, format);
    });

    __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].filter('date', function (value) {
        return __WEBPACK_IMPORTED_MODULE_3_src_core_service_util_service__["default"].date(value);
    });

    return {
        createInstance: createInstance,
        initComponents: initComponents,
        createComponent: createComponent,
        getComponent: getComponent,
        getComponents: getComponents,
        getWrapper: getWrapper,
        getName: getName
    };

    function createInstance(renderElement, router, providers) {
        var components = getComponents();

        return new __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */]({
            el: renderElement,
            router: router,
            components: components,
            template: '<sw-admin />',
            provide: function provide() {
                return providers;
            }
        });
    }

    function initComponents() {
        var componentRegistry = Shopware.Component.getRegistry();

        componentRegistry.forEach(function (component) {
            createComponent(component.name);
        });

        return vueComponents;
    }

    function createComponent(componentName) {
        var componentConfig = Shopware.Component.build(componentName);

        if (!componentConfig) {
            return false;
        }

        var vueComponent = __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */].component(componentName, componentConfig);

        vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    function getComponent(componentName) {
        if (!vueComponents[componentName]) {
            return null;
        }

        return vueComponents[componentName];
    }

    function getComponents() {
        return vueComponents;
    }

    function getWrapper() {
        return __WEBPACK_IMPORTED_MODULE_1_vue__["a" /* default */];
    }

    function getName() {
        return 'Vue.js';
    }
}

/***/ }),
/* 344 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__atom_grid_sw_pagination_sw_pagination__ = __webpack_require__(345);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__atom_form_sw_form__ = __webpack_require__(348);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__atom_form_sw_field__ = __webpack_require__(350);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3__atom_form_sw_form_grid__ = __webpack_require__(353);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__atom_form_sw_multi_select__ = __webpack_require__(356);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5__atom_utils_sw_loader_sw_loader__ = __webpack_require__(359);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6__atom_sw_card__ = __webpack_require__(362);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7__atom_sw_button__ = __webpack_require__(365);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_8__grid_sw_grid__ = __webpack_require__(368);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_9__grid_sw_grid_column__ = __webpack_require__(371);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_10__organism_sw_admin_sw_admin__ = __webpack_require__(374);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_11__organism_sw_desktop_sw_desktop__ = __webpack_require__(376);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_12__organism_sw_sidebar_sw_sidebar__ = __webpack_require__(379);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_13__organism_sw_search_bar_sw_search_bar__ = __webpack_require__(382);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_14__organism_sw_workspace__ = __webpack_require__(385);



















/***/ }),
/* 345 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_grid_sw_pagination_sw_pagination_less__ = __webpack_require__(346);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_grid_sw_pagination_sw_pagination_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_grid_sw_pagination_sw_pagination_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_grid_sw_pagination_sw_pagination_html_twig__ = __webpack_require__(347);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_grid_sw_pagination_sw_pagination_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_grid_sw_pagination_sw_pagination_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-pagination', {

    props: ['page', 'maxPage', 'total', 'limit'],

    data: function data() {
        return {
            currentPage: this.page,
            perPage: this.limit,
            steps: [25, 50, 75, 100]
        };
    },


    methods: {
        pageChange: function pageChange() {
            this.$emit('page-change', {
                page: this.currentPage,
                limit: this.perPage
            });
        },
        firstPage: function firstPage() {
            this.currentPage = 1;
            this.pageChange();
        },
        prevPage: function prevPage() {
            this.currentPage -= 1;
            this.pageChange();
        },
        nextPage: function nextPage() {
            this.currentPage += 1;
            this.pageChange();
        },
        lastPage: function lastPage() {
            this.currentPage = this.maxPage;
            this.pageChange();
        },
        refresh: function refresh() {
            this.pageChange();
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_grid_sw_pagination_sw_pagination_html_twig___default.a
}));

/***/ }),
/* 346 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 347 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-pagination\">\n\n    <button class=\"btn--first-page\" :disabled=\"page === 1\" @click=\"firstPage\">\n        <i class=\"icon-arrow-thin-left\"></i>\n    </button>\n\n    <button class=\"btn--prev-page\" :disabled=\"page === 1\" @click=\"prevPage\">\n        <i class=\"icon-chevron-left\"></i>\n    </button>\n\n    <div class=\"page-selection\">\n        Page\n        <input type=\"number\" class=\"page-selection--field\" name=\"current-page-number\" min=\"1\" :max=\"maxPage\" :value=\"currentPage\" v-model=\"currentPage\" @change=\"pageChange\">\n        of <span class=\"number\">{{ maxPage }}</span>\n    </div>\n\n    <button class=\"btn--next-page\" :disabled=\"page === maxPage\" @click=\"nextPage\">\n        <i class=\"icon-chevron-right\"></i>\n    </button>\n\n    <button class=\"btn--last-page\" :disabled=\"page === maxPage\" @click=\"lastPage\">\n        <i class=\"icon-arrow-thin-right\"></i>\n    </button>\n\n    <button class=\"btn--refresh\" @click=\"refresh\">\n        <i class=\"icon-clockwise\"></i>\n    </button>\n\n    <div class=\"per-page\">\n        Items per page:\n        <select class=\"per-page--select\" :value=\"perPage\" v-model=\"perPage\" @change=\"pageChange\">\n            <option v-for=\"step in steps\" :value=\"step\">\n                {{ step }}\n            </option>\n        </select>\n    </div>\n\n    <div class=\"page-total\">\n        Items total: <span class=\"number\">{{ total }}</span>\n    </div>\n</div>";

/***/ }),
/* 348 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_form_html_twig__ = __webpack_require__(349);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_form_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_form_html_twig__);


/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-form', {
    props: {
        method: {
            type: String,
            default: 'GET'
        },

        action: {
            type: String,
            default: '',
            required: true
        },

        ajax: {
            type: Boolean,
            default: true
        }
    },

    computed: {
        formMethod: function formMethod() {
            return this.method.toUpperCase();
        }
    },

    methods: {
        handleSubmit: function handleSubmit(e) {
            if (!this.ajax) {
                return;
            }
            e.preventDefault();

            var data = new FormData(this.$refs.form);
            this.$emit('submit-started', data);
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0__sw_form_html_twig___default.a
}));

/***/ }),
/* 349 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-form\">\n    <form :method=\"formMethod\" :action=\"action\" @submit=\"handleSubmit\" ref=\"form\">\n        <slot></slot>\n    </form>\n</div>";

/***/ }),
/* 350 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_field_less__ = __webpack_require__(351);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_field_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_field_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_field_html_twig__ = __webpack_require__(352);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_field_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__sw_field_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-field', {
    props: {
        type: {
            type: String,
            default: 'text'
        },
        id: {
            type: String,
            required: false
        },
        name: {
            type: String,
            required: false
        },
        placeholder: {
            type: String,
            default: '',
            required: false
        },
        value: {
            type: [String, Boolean, Number, Date],
            default: ''
        },
        suffix: {
            type: String,
            default: '',
            required: false
        },
        label: {
            type: String,
            default: ''
        },
        options: {
            type: Array,
            default: function _default() {
                return [];
            },
            required: false
        },
        isCurrency: {
            type: Boolean,
            default: false,
            required: false
        }
    },
    template: __WEBPACK_IMPORTED_MODULE_1__sw_field_html_twig___default.a
}));

/***/ }),
/* 351 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 352 */
/***/ (function(module, exports) {

module.exports = "<div :class=\"['sw-field', suffix.length ? 'has--suffix': '']\">\n\n    <!-- Label -->\n    <label v-if=\"label\" :for=\"id\">{{ label }}</label>\n\n    <!-- Textarea -->\n    <textarea v-if=\"type === 'textarea'\" v-html=\"value\" :name=\"name\" :id=\"id\" :placeholder=\"placeholder\" @input=\"$emit('input', $event.target.value)\"></textarea>\n\n    <!-- Combobox -->\n    <div class=\"sw-field-select\" v-else-if=\"type === 'select' || type === 'combo'\">\n        <select name=\"name\" :id=\"id\" @input=\"$emit('input', $event.target.value)\" :value=\"value\">\n            <template v-if=\"options.length\">\n                <option v-for=\"option in options\" :value=\"option.value\">{{ option.label }}</option>\n            </template>\n            <slot></slot>\n        </select>\n    </div>\n\n    <!-- Checkbox -->\n    <input v-else-if=\"type === 'checkbox'\" type=\"checkbox\" :name=\"name\" :id=\"id\" :value=\"value\" @input=\"$emit('input', $event.target.value)\">\n\n    <!-- Date -->\n    <input v-else-if=\"type === 'datetime'\" type=\"datetime-local\" :name=\"name\" :id=\"id\" :value=\"value\" @input=\"$emit('input', $event.target.value)\">\n\n    <!-- Default Input -->\n    <input v-else :type=\"type\" :name=\"name\" :id=\"id\" :placeholder=\"placeholder\" :value=\"value\" @input=\"$emit('input', $event.target.value)\" />\n\n    <!-- Suffix Text -->\n    <span v-if=\"suffix\" class=\"sw-form-field--suffix\">\n        {{ suffix }}\n    </span>\n</div>";

/***/ }),
/* 353 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_form_grid_html_twig__ = __webpack_require__(354);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_form_grid_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_form_grid_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_form_grid_less__ = __webpack_require__(355);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_form_grid_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__sw_form_grid_less__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-form-grid', {
    props: {
        columns: Array,
        items: Array
    },

    computed: {},

    methods: {},

    template: __WEBPACK_IMPORTED_MODULE_0__sw_form_grid_html_twig___default.a
}));

/***/ }),
/* 354 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-form-grid\">\n    <div class=\"grid--header\">\n        <slot name=\"header\">\n            <div class=\"grid--cell\" v-for=\"column in columns\">{{ column.label }}</div>\n        </slot>\n    </div>\n    <div class=\"grid--body\">\n        <slot name=\"body\">\n            <div class=\"grid--item\" v-for=\"item in items\">\n                <div class=\"grid--cell\" v-for=\"column in columns\">\n                    <sw-field v-if=\"column.type === 'select'\" v-model=\"item[column.field]\" type=\"select\" :options=\"column.options\" :value=\"item[column.field]\"></sw-field>\n                    <input v-if=\"column.type === 'number'\" v-model.number=\"item[column.field]\" type=\"number\" :value=\"item[column.field]\">\n                </div>\n            </div>\n        </slot>\n    </div>\n</div>\n";

/***/ }),
/* 355 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 356 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_multi_select_html_twig__ = __webpack_require__(357);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_multi_select_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_multi_select_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_multi_select_less__ = __webpack_require__(358);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_multi_select_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__sw_multi_select_less__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-multi-select', {
    props: {
        serviceProvider: {
            type: Object,
            required: true
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        values: {
            type: Array,
            required: true,
            default: function _default() {
                return [];
            }
        },
        label: {
            type: String,
            default: ''
        },
        id: {
            type: String,
            required: true
        }
    },

    data: function data() {
        return {
            searchTerm: '',
            isExpanded: false,
            entries: []
        };
    },


    computed: {
        filteredEntries: function filteredEntries() {
            var searchTerm = this.searchTerm.toLowerCase();
            return this.entries.filter(function (entry) {
                var entryName = entry.name.toLowerCase();
                return entryName.indexOf(searchTerm) !== -1;
            });
        },
        stringifyValues: function stringifyValues() {
            return this.values.join('|');
        }
    },

    watch: {
        searchTerm: 'onSearchTermChange'
    },

    created: function created() {
        var _this = this;

        this.serviceProvider.getList(100, 0).then(function (response) {
            _this.entries = response.data;
        });
    },


    methods: {
        onDismissEntry: function onDismissEntry(uuid) {
            this.values = this.values.filter(function (entry) {
                return entry.uuid !== uuid;
            });

            this.$emit('input', this.values);
        },
        onSearchTermChange: function onSearchTermChange() {
            this.isExpanded = this.searchTerm.length > 3 && this.filteredEntries.length > 0;
        },
        onSelectEntry: function onSelectEntry(uuid) {
            if (!uuid) {
                return false;
            }

            var selectedEntry = this.entries.find(function (item) {
                return item.uuid === uuid;
            });

            if (!selectedEntry) {
                return false;
            }

            this.values.push(selectedEntry);

            this.searchTerm = '';

            this.$emit('input', this.values);

            return selectedEntry;
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0__sw_multi_select_html_twig___default.a
}));

/***/ }),
/* 357 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-multi-select\">\n    <label v-if=\"label\" :for=\"id\">{{ label }}</label>\n\n    <div class=\"sw-multi-select--inner\">\n        <input type=\"hidden\" :value=\"stringifyValues\">\n        <ul class=\"sw-multi-select--list\">\n\n            <!-- Loop through the selected values -->\n            <li class=\"list--value\" v-for=\"entry in values\" :key=\"entry.uuid\">\n                <span class=\"value--display\">{{ entry.name }}</span>\n                <a href=\"#\" class=\"value--dismiss\" @click.prevent=\"onDismissEntry(entry.uuid)\">&#x2715;</a>\n            </li>\n\n            <li class=\"list--input\">\n                <input class=\"input--element\" type=\"text\" :placeholder=\"placeholder\" v-model=\"searchTerm\">\n            </li>\n        </ul>\n\n        <transition name=\"fade\">\n            <div class=\"sw-drop-down\" v-if=\"isExpanded\">\n                <ul class=\"sw-drop-down--list\">\n                    <li v-for=\"entry in filteredEntries\"\n                        class=\"list--entry\"\n                        :data-uuid=\"entry.uuid\"\n                        @click=\"onSelectEntry(entry.uuid, entry.name)\"\n                    >\n                        {{ entry.name }}\n                    </li>\n                </ul>\n            </div>\n        </transition>\n    </div>\n</div>";

/***/ }),
/* 358 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 359 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_utils_sw_loader_sw_loader_less__ = __webpack_require__(360);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_utils_sw_loader_sw_loader_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_utils_sw_loader_sw_loader_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_utils_sw_loader_sw_loader_html_twig__ = __webpack_require__(361);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_utils_sw_loader_sw_loader_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_utils_sw_loader_sw_loader_html_twig__);




/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-loader', {
    template: __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_utils_sw_loader_sw_loader_html_twig___default.a
}));

/***/ }),
/* 360 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 361 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-loader--overlay\">\n    <div class=\"sw-loader\">\n        <div class=\"sw-loader--elements element-1\"></div>\n        <div class=\"sw-loader--elements element-2\"></div>\n        <div class=\"sw-loader--elements element-3\"></div>\n        <div class=\"sw-loader--elements element-4\"></div>\n    </div>\n</div>";

/***/ }),
/* 362 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_card_sw_card_html_twig__ = __webpack_require__(363);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_card_sw_card_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_card_sw_card_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_card_sw_card_less__ = __webpack_require__(364);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_card_sw_card_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_card_sw_card_less__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-card', {
    props: {
        title: {
            type: String,
            required: true
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_card_sw_card_html_twig___default.a
}));

/***/ }),
/* 363 */
/***/ (function(module, exports) {

module.exports = "<div class=\"container--card\">\n    <div class=\"card--title\">\n        {{ title }}\n    </div>\n    <slot></slot>\n</div>";

/***/ }),
/* 364 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 365 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_button_sw_button_html_twig__ = __webpack_require__(366);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_button_sw_button_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_button_sw_button_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_button_sw_button_less__ = __webpack_require__(367);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_button_sw_button_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_atom_sw_button_sw_button_less__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-button', {
    props: {
        isPrimary: {
            type: Boolean,
            required: false,
            default: false
        },
        isDisabled: {
            type: Boolean,
            required: false,
            default: false
        },
        link: {
            type: String,
            required: false,
            default: ''
        }
    },
    template: __WEBPACK_IMPORTED_MODULE_0_src_app_component_atom_sw_button_sw_button_html_twig___default.a
}));

/***/ }),
/* 366 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-button\">\n    <router-link v-if=\"link.length\" :to=\"{ 'name': link }\" :class=\"['sw-button--inner', isPrimary ? 'is--primary' : 'is--secondary']\">\n        <slot></slot>\n    </router-link>\n\n    <button :class=\"['sw-button--inner', isPrimary ? 'is--primary' : 'is--secondary']\" :disabled=\"isDisabled\" v-on=\"$listeners\" v-else>\n        <slot></slot>\n    </button>\n</div>";

/***/ }),
/* 367 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 368 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_grid_less__ = __webpack_require__(369);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_grid_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_grid_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_grid_html_twig__ = __webpack_require__(370);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_grid_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__sw_grid_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-grid', {
    data: function data() {
        return {
            columns: []
        };
    },


    props: {
        items: {
            type: Array,
            required: false,
            default: null
        },

        actions: {
            type: Array,
            required: false,
            default: function _default() {
                return ['edit', 'delete', 'duplicate'];
            }
        },

        selectable: {
            type: Boolean,
            required: false,
            default: true
        },

        sidebar: {
            type: Boolean,
            required: false,
            default: false
        },

        header: {
            type: Boolean,
            required: false,
            default: true
        },

        pagination: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        columnFlex: function columnFlex() {
            var flex = this.selectable === true ? '50px ' : '';

            this.columns.forEach(function (column) {
                if ('' + parseInt(column.flex, 10) === column.flex) {
                    flex += column.flex + 'fr ';
                } else {
                    flex += column.flex + ' ';
                }
            });

            if (this.actions.length > 0) {
                flex += '140px';
            }

            return {
                'grid-template-columns': flex.trim()
            };
        }
    },

    updated: function updated() {
        console.log('Items', this);
    },


    watch: {
        items: function items(_items) {
            var _this = this;

            _items.forEach(function (item) {
                if (!item.selected) {
                    _this.$set(item, 'selected', false);
                }
            });
        }
    },

    methods: {
        selectAll: function selectAll(selected) {
            var _this2 = this;

            this.items.forEach(function (item) {
                _this2.$set(item, 'selected', selected);
            });
        },
        getSelection: function getSelection() {
            return this.items.filter(function (item) {
                return item.selected;
            });
        },
        getScrollBarWidth: function getScrollBarWidth() {
            if (!this.$el) {
                return 0;
            }

            var gridBody = this.$el.getElementsByClassName('sw-grid--body')[0];

            if (gridBody.offsetWidth && gridBody.clientWidth) {
                return gridBody.offsetWidth - gridBody.clientWidth;
            }

            return 0;
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_1__sw_grid_html_twig___default.a
}));

/***/ }),
/* 369 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 370 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-grid\" :class=\"{ 'has--sidebar': sidebar  }\">\n\n    <slot name=\"content\">\n        <div class=\"sw-grid--content\" :class=\"{ 'has--header': header, 'has--pagination': pagination }\">\n\n            <slot name=\"header\" v-if=\"header\">\n                <div class=\"sw-grid--header\" :style=\"columnFlex\">\n                    <div class=\"sw-grid--cell\" v-if=\"selectable\"><input type=\"checkbox\" v-on:change=\"selectAll($event.target.checked)\"></div>\n                    <div class=\"sw-grid--cell\" v-for=\"column in columns\">{{ column.label }}</div>\n                    <div class=\"sw-grid--cell\" v-if=\"actions.length > 0\">Actions</div>\n                </div>\n            </slot>\n\n            <slot name=\"body\">\n                <div class=\"sw-grid--body\">\n\n                    <slot name=\"items\" v-for=\"item in items\">\n                        <div class=\"sw-grid--item\" :style=\"columnFlex\" :class=\"{ 'is--selected': item.selected }\">\n\n                            <div class=\"sw-grid--cell\" v-if=\"selectable\"><input type=\"checkbox\" v-model=\"item.selected\"></div>\n\n                            <slot name=\"columns\" :item=\"item\"></slot>\n\n                            <slot name=\"actions\" v-if=\"actions.length > 0\">\n                                <div class=\"sw-grid--cell sw-grid--actions\">\n                                    <button v-on:click=\"$emit('edit', item)\" v-if=\"actions.indexOf('edit') !== -1\"><i class=\"icon-document-edit\"></i></button>\n                                    <button v-on:click=\"$emit('delete', item)\" v-if=\"actions.indexOf('delete') !== -1\"><i class=\"icon-document-delete\"></i></button>\n                                    <button v-on:click=\"$emit('duplicate', item)\" v-if=\"actions.indexOf('duplicate') !== -1\"><i class=\"icon-copy\"></i></button>\n                                </div>\n                            </slot>\n                        </div>\n                    </slot>\n\n                </div>\n            </slot>\n\n            <div class=\"sw-grid--pagination\" v-if=\"pagination\">\n                <slot name=\"pagination\"></slot>\n            </div>\n        </div>\n    </slot>\n\n    <div class=\"sw-grid--sidebar\" v-if=\"sidebar\">\n        <slot name=\"sidebar\">\n            <button><i class=\"icon-gear\"></i></button>\n            <button><i class=\"icon-clockwise\"></i></button>\n            <button><i class=\"icon-experiment\"></i></button>\n        </slot>\n    </div>\n</div>";

/***/ }),
/* 371 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_grid_column_less__ = __webpack_require__(372);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__sw_grid_column_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0__sw_grid_column_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_grid_column_html_twig__ = __webpack_require__(373);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__sw_grid_column_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1__sw_grid_column_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-grid-column', {

    props: {
        label: {
            type: String,
            required: true
        },
        flex: {
            required: false,
            default: 1
        }
    },

    created: function created() {
        this.registerColumn();
    },


    methods: {
        registerColumn: function registerColumn() {
            var _this = this;

            var hasColumn = this.$parent.columns.findIndex(function (column) {
                return column.label === _this.label;
            });

            if (hasColumn === -1) {
                this.$parent.columns.push({
                    label: this.label,
                    flex: this.flex
                });
            }
        }
    },

    template: __WEBPACK_IMPORTED_MODULE_1__sw_grid_column_html_twig___default.a
}));

/***/ }),
/* 372 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 373 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-grid--column sw-grid--cell\">\n    <slot></slot>\n</div>";

/***/ }),
/* 374 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_admin_sw_admin_html_twig__ = __webpack_require__(375);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_admin_sw_admin_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_admin_sw_admin_html_twig__);


/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-admin', {
    template: __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_admin_sw_admin_html_twig___default.a
}));

/***/ }),
/* 375 */
/***/ (function(module, exports) {

module.exports = "<div id=\"app\">\n    <router-view></router-view>\n</div>";

/***/ }),
/* 376 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_desktop_sw_desktop_less__ = __webpack_require__(377);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_desktop_sw_desktop_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_desktop_sw_desktop_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_desktop_sw_desktop_html_twig__ = __webpack_require__(378);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_desktop_sw_desktop_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_desktop_sw_desktop_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-desktop', {
    template: __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_desktop_sw_desktop_html_twig___default.a
}));

/***/ }),
/* 377 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 378 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-desktop\">\n    <sw-sidebar class=\"main-sidebar\"></sw-sidebar>\n\n    <div class=\"desktop\">\n        <sw-search-bar id=\"main-toolbar\"></sw-search-bar>\n        <router-view></router-view>\n    </div>\n</div>";

/***/ }),
/* 379 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_sidebar_sw_sidebar_less__ = __webpack_require__(380);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_sidebar_sw_sidebar_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_sidebar_sw_sidebar_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_sidebar_sw_sidebar_html_twig__ = __webpack_require__(381);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_sidebar_sw_sidebar_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_sidebar_sw_sidebar_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-sidebar', {
    inject: ['menuService'],
    template: __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_sidebar_sw_sidebar_html_twig___default.a,

    computed: {
        mainMenuEntries: function mainMenuEntries() {
            return this.menuService.getMainMenu();
        }
    },

    methods: {
        getIconName: function getIconName(name) {
            return 'icon-' + name;
        }
    }
}));

/***/ }),
/* 380 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 381 */
/***/ (function(module, exports) {

module.exports = "<aside class=\"sw--sidebar is--collapsed\">\n    <div class=\"sidebar--logo\">\n        <div class=\"shopware--logo\"></div>\n        <div class=\"logo--text\">Admin Prototype<br><span class=\"text--suffix\">Shopware Administration</span></div>\n    </div>\n\n    <nav class=\"main-navigation\">\n        <ul class=\"nav\">\n            <li v-for=\"entry, routeName in mainMenuEntries\" class=\"nav--link\">\n                <router-link :to=\"{ name: routeName}\">\n                    <i :class=\"getIconName(entry.icon)\" :style=\"{ 'color': entry.color }\"></i>\n                    {{ entry.name }}\n                </router-link>\n            </li>\n        </ul>\n    </nav>\n</aside>";

/***/ }),
/* 382 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_search_bar_sw_search_bar_less__ = __webpack_require__(383);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_search_bar_sw_search_bar_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_search_bar_sw_search_bar_less__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_search_bar_sw_search_bar_html_twig__ = __webpack_require__(384);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_search_bar_sw_search_bar_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_search_bar_sw_search_bar_html_twig__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-search-bar', {
    template: __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_search_bar_sw_search_bar_html_twig___default.a
}));

/***/ }),
/* 383 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 384 */
/***/ (function(module, exports) {

module.exports = "<div class=\"sw-search-bar\">\n\n</div>";

/***/ }),
/* 385 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_workspace_sw_workspace_html_twig__ = __webpack_require__(386);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_workspace_sw_workspace_html_twig___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_workspace_sw_workspace_html_twig__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_workspace_sw_workspace_less__ = __webpack_require__(387);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_workspace_sw_workspace_less___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_src_app_component_organism_sw_workspace_sw_workspace_less__);



/* unused harmony default export */ var _unused_webpack_default_export = (Shopware.Component.register('sw-workspace', {
    template: __WEBPACK_IMPORTED_MODULE_0_src_app_component_organism_sw_workspace_sw_workspace_html_twig___default.a,
    computed: {
        iconClassName: function iconClassName() {
            if (!this.icon) {
                return 'icon--empty';
            }

            return 'icon-' + this.icon;
        }
    },

    data: function data() {
        return {
            title: '',
            icon: '',
            primaryColor: '',
            parentRoute: ''
        };
    },


    props: ['name'],

    created: function created() {
        this.setupWorkspace();
    },
    updated: function updated() {
        this.setupWorkspace();
    },


    methods: {
        setupWorkspace: function setupWorkspace() {
            var module = this.$route.meta.$module;

            if (!module) {
                return;
            }

            this.icon = module.icon;
            this.title = module.name;
            this.primaryColor = module.color;

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        }
    }
}));

/***/ }),
/* 386 */
/***/ (function(module, exports) {

module.exports = "<div class=\"workspace-container\">\n    <div class=\"workspace--toolbar\" :style=\"{ 'border-bottom-color': primaryColor }\">\n\n        <!-- Contains the name and icon of the parent root if defined -->\n        <router-link :to=\"{ name: parentRoute }\" v-if=\"parentRoute\" :style=\"{ 'color': primaryColor }\" class=\"toolbar--back-btn\">\n            <i class=\"chevron\">&#8249;</i>\n            <i :class=\"iconClassName\"></i>\n        </router-link>\n\n        <!-- Mainly for primary actions like saving, canceling, etc. -->\n        <slot name=\"header\">\n            <header class=\"toolbar--middle-section\">\n                <h1 class=\"workspace--headline\">\n                    {{ title }}\n                </h1>\n            </header>\n        </slot>\n\n        <slot name=\"header-tools\"></slot>\n    </div>\n\n    <div class=\"workspace--content\">\n        <main class=\"content--main\">\n            <slot></slot>\n        </main>\n        <aside class=\"content--sidebar\">\n            <!-- <router-view name=\"sidebar\"></router-view> -->\n        </aside>\n    </div>\n</div>";

/***/ }),
/* 387 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),
/* 388 */,
/* 389 */,
/* 390 */,
/* 391 */,
/* 392 */
/***/ (function(module, exports, __webpack_require__) {

var map = {
	"./af": 102,
	"./af.js": 102,
	"./ar": 103,
	"./ar-dz": 104,
	"./ar-dz.js": 104,
	"./ar-kw": 105,
	"./ar-kw.js": 105,
	"./ar-ly": 106,
	"./ar-ly.js": 106,
	"./ar-ma": 107,
	"./ar-ma.js": 107,
	"./ar-sa": 108,
	"./ar-sa.js": 108,
	"./ar-tn": 109,
	"./ar-tn.js": 109,
	"./ar.js": 103,
	"./az": 110,
	"./az.js": 110,
	"./be": 111,
	"./be.js": 111,
	"./bg": 112,
	"./bg.js": 112,
	"./bm": 113,
	"./bm.js": 113,
	"./bn": 114,
	"./bn.js": 114,
	"./bo": 115,
	"./bo.js": 115,
	"./br": 116,
	"./br.js": 116,
	"./bs": 117,
	"./bs.js": 117,
	"./ca": 118,
	"./ca.js": 118,
	"./cs": 119,
	"./cs.js": 119,
	"./cv": 120,
	"./cv.js": 120,
	"./cy": 121,
	"./cy.js": 121,
	"./da": 122,
	"./da.js": 122,
	"./de": 123,
	"./de-at": 124,
	"./de-at.js": 124,
	"./de-ch": 125,
	"./de-ch.js": 125,
	"./de.js": 123,
	"./dv": 126,
	"./dv.js": 126,
	"./el": 127,
	"./el.js": 127,
	"./en-au": 128,
	"./en-au.js": 128,
	"./en-ca": 129,
	"./en-ca.js": 129,
	"./en-gb": 130,
	"./en-gb.js": 130,
	"./en-ie": 131,
	"./en-ie.js": 131,
	"./en-nz": 132,
	"./en-nz.js": 132,
	"./eo": 133,
	"./eo.js": 133,
	"./es": 134,
	"./es-do": 135,
	"./es-do.js": 135,
	"./es-us": 136,
	"./es-us.js": 136,
	"./es.js": 134,
	"./et": 137,
	"./et.js": 137,
	"./eu": 138,
	"./eu.js": 138,
	"./fa": 139,
	"./fa.js": 139,
	"./fi": 140,
	"./fi.js": 140,
	"./fo": 141,
	"./fo.js": 141,
	"./fr": 142,
	"./fr-ca": 143,
	"./fr-ca.js": 143,
	"./fr-ch": 144,
	"./fr-ch.js": 144,
	"./fr.js": 142,
	"./fy": 145,
	"./fy.js": 145,
	"./gd": 146,
	"./gd.js": 146,
	"./gl": 147,
	"./gl.js": 147,
	"./gom-latn": 148,
	"./gom-latn.js": 148,
	"./gu": 149,
	"./gu.js": 149,
	"./he": 150,
	"./he.js": 150,
	"./hi": 151,
	"./hi.js": 151,
	"./hr": 152,
	"./hr.js": 152,
	"./hu": 153,
	"./hu.js": 153,
	"./hy-am": 154,
	"./hy-am.js": 154,
	"./id": 155,
	"./id.js": 155,
	"./is": 156,
	"./is.js": 156,
	"./it": 157,
	"./it.js": 157,
	"./ja": 158,
	"./ja.js": 158,
	"./jv": 159,
	"./jv.js": 159,
	"./ka": 160,
	"./ka.js": 160,
	"./kk": 161,
	"./kk.js": 161,
	"./km": 162,
	"./km.js": 162,
	"./kn": 163,
	"./kn.js": 163,
	"./ko": 164,
	"./ko.js": 164,
	"./ky": 165,
	"./ky.js": 165,
	"./lb": 166,
	"./lb.js": 166,
	"./lo": 167,
	"./lo.js": 167,
	"./lt": 168,
	"./lt.js": 168,
	"./lv": 169,
	"./lv.js": 169,
	"./me": 170,
	"./me.js": 170,
	"./mi": 171,
	"./mi.js": 171,
	"./mk": 172,
	"./mk.js": 172,
	"./ml": 173,
	"./ml.js": 173,
	"./mr": 174,
	"./mr.js": 174,
	"./ms": 175,
	"./ms-my": 176,
	"./ms-my.js": 176,
	"./ms.js": 175,
	"./my": 177,
	"./my.js": 177,
	"./nb": 178,
	"./nb.js": 178,
	"./ne": 179,
	"./ne.js": 179,
	"./nl": 180,
	"./nl-be": 181,
	"./nl-be.js": 181,
	"./nl.js": 180,
	"./nn": 182,
	"./nn.js": 182,
	"./pa-in": 183,
	"./pa-in.js": 183,
	"./pl": 184,
	"./pl.js": 184,
	"./pt": 185,
	"./pt-br": 186,
	"./pt-br.js": 186,
	"./pt.js": 185,
	"./ro": 187,
	"./ro.js": 187,
	"./ru": 188,
	"./ru.js": 188,
	"./sd": 189,
	"./sd.js": 189,
	"./se": 190,
	"./se.js": 190,
	"./si": 191,
	"./si.js": 191,
	"./sk": 192,
	"./sk.js": 192,
	"./sl": 193,
	"./sl.js": 193,
	"./sq": 194,
	"./sq.js": 194,
	"./sr": 195,
	"./sr-cyrl": 196,
	"./sr-cyrl.js": 196,
	"./sr.js": 195,
	"./ss": 197,
	"./ss.js": 197,
	"./sv": 198,
	"./sv.js": 198,
	"./sw": 199,
	"./sw.js": 199,
	"./ta": 200,
	"./ta.js": 200,
	"./te": 201,
	"./te.js": 201,
	"./tet": 202,
	"./tet.js": 202,
	"./th": 203,
	"./th.js": 203,
	"./tl-ph": 204,
	"./tl-ph.js": 204,
	"./tlh": 205,
	"./tlh.js": 205,
	"./tr": 206,
	"./tr.js": 206,
	"./tzl": 207,
	"./tzl.js": 207,
	"./tzm": 208,
	"./tzm-latn": 209,
	"./tzm-latn.js": 209,
	"./tzm.js": 208,
	"./uk": 210,
	"./uk.js": 210,
	"./ur": 211,
	"./ur.js": 211,
	"./uz": 212,
	"./uz-latn": 213,
	"./uz-latn.js": 213,
	"./uz.js": 212,
	"./vi": 214,
	"./vi.js": 214,
	"./x-pseudo": 215,
	"./x-pseudo.js": 215,
	"./yo": 216,
	"./yo.js": 216,
	"./zh-cn": 217,
	"./zh-cn.js": 217,
	"./zh-hk": 218,
	"./zh-hk.js": 218,
	"./zh-tw": 219,
	"./zh-tw.js": 219
};
function webpackContext(req) {
	return __webpack_require__(webpackContextResolve(req));
};
function webpackContextResolve(req) {
	var id = map[req];
	if(!(id + 1)) // check for number or string
		throw new Error("Cannot find module '" + req + "'.");
	return id;
};
webpackContext.keys = function webpackContextKeys() {
	return Object.keys(map);
};
webpackContext.resolve = webpackContextResolve;
module.exports = webpackContext;
webpackContext.id = 392;

/***/ }),
/* 393 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = ViewFactory;
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
/* 394 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = initializeRouter;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_vue_router__ = __webpack_require__(101);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_factory_router_factory__ = __webpack_require__(395);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_app_routes__ = __webpack_require__(396);






function initializeRouter(container) {
    var factory = Object(__WEBPACK_IMPORTED_MODULE_1_src_core_factory_router_factory__["a" /* default */])(__WEBPACK_IMPORTED_MODULE_0_vue_router__["a" /* default */], container.view);
    factory.addRoutes(__WEBPACK_IMPORTED_MODULE_2_src_app_routes__["a" /* default */]);
    factory.addModuleRoutes(container.coreModuleRoutes);

    return factory.createRouterInstance();
}

/***/ }),
/* 395 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = createRouter;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__ = __webpack_require__(17);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray__ = __webpack_require__(38);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_toConsumableArray__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_assign__ = __webpack_require__(21);
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

            var moduleRegistry = Shopware.Module.getRegistry();

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

/***/ }),
/* 396 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony default export */ __webpack_exports__["a"] = ([{
    path: '/core',
    alias: '/',
    name: 'core',
    coreRoute: true,
    root: true,
    component: 'sw-desktop'
}]);

/***/ }),
/* 397 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export (immutable) */ __webpack_exports__["a"] = MenuService;
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_defineProperty__ = __webpack_require__(64);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_defineProperty___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_defineProperty__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__ = __webpack_require__(17);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__ = __webpack_require__(10);




var ModuleFactory = Shopware.Module;

function MenuService() {
    return {
        getMainMenu: getMainMenu
    };
}

function getMainMenu() {
    var modules = ModuleFactory.getRegistry();
    var menuEntries = {};

    modules.forEach(function (module) {
        if (!Object.prototype.hasOwnProperty.bind(module, 'navigation') || !module.navigation) {
            return;
        }

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_object_keys___default()(module.navigation).forEach(function (navigationKey) {
            var menuEntry = module.navigation[navigationKey];
            __WEBPACK_IMPORTED_MODULE_2_src_core_service_util_service__["default"].merge(menuEntries, __WEBPACK_IMPORTED_MODULE_0_babel_runtime_helpers_defineProperty___default()({}, navigationKey, menuEntry));
        });
    });

    return menuEntries.root[0];
}

/***/ }),
/* 398 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_src_core_service_api_shop_api_service__ = __webpack_require__(399);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_src_core_service_api_category_api_service__ = __webpack_require__(409);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_src_core_service_api_product_api_service__ = __webpack_require__(410);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_src_core_service_api_product_manufacturer_api_service__ = __webpack_require__(411);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_src_core_service_api_order_api_service__ = __webpack_require__(412);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_src_core_service_api_order_line_item_api_service__ = __webpack_require__(413);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_src_core_service_api_order_delivery_api_service__ = __webpack_require__(414);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7_src_core_service_api_order_state_api_service__ = __webpack_require__(415);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_8_src_core_service_api_customer_api_service__ = __webpack_require__(416);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_9_src_core_service_api_customer_group_api_service__ = __webpack_require__(417);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_10_src_core_service_api_payment_method_api_service__ = __webpack_require__(418);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_11_src_core_service_api_shipping_method_api_service__ = __webpack_require__(419);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_12_src_core_service_api_country_api_service__ = __webpack_require__(420);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_13_src_core_service_api_currency_api_service__ = __webpack_require__(421);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_14_src_core_service_api_tax_api_service__ = __webpack_require__(422);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_15_src_core_service_api_media_api_service__ = __webpack_require__(423);

















/* harmony default export */ __webpack_exports__["a"] = ([{ name: 'shopService', provider: __WEBPACK_IMPORTED_MODULE_0_src_core_service_api_shop_api_service__["a" /* default */] }, { name: 'categoryService', provider: __WEBPACK_IMPORTED_MODULE_1_src_core_service_api_category_api_service__["a" /* default */] }, { name: 'productService', provider: __WEBPACK_IMPORTED_MODULE_2_src_core_service_api_product_api_service__["a" /* default */] }, { name: 'productManufacturerService', provider: __WEBPACK_IMPORTED_MODULE_3_src_core_service_api_product_manufacturer_api_service__["a" /* default */] }, { name: 'orderService', provider: __WEBPACK_IMPORTED_MODULE_4_src_core_service_api_order_api_service__["a" /* default */] }, { name: 'orderLineItemService', provider: __WEBPACK_IMPORTED_MODULE_5_src_core_service_api_order_line_item_api_service__["a" /* default */] }, { name: 'orderDeliveryService', provider: __WEBPACK_IMPORTED_MODULE_6_src_core_service_api_order_delivery_api_service__["a" /* default */] }, { name: 'orderStateService', provider: __WEBPACK_IMPORTED_MODULE_7_src_core_service_api_order_state_api_service__["a" /* default */] }, { name: 'customerService', provider: __WEBPACK_IMPORTED_MODULE_8_src_core_service_api_customer_api_service__["a" /* default */] }, { name: 'customerGroupService', provider: __WEBPACK_IMPORTED_MODULE_9_src_core_service_api_customer_group_api_service__["a" /* default */] }, { name: 'paymentMethodService', provider: __WEBPACK_IMPORTED_MODULE_10_src_core_service_api_payment_method_api_service__["a" /* default */] }, { name: 'shippingMethodService', provider: __WEBPACK_IMPORTED_MODULE_11_src_core_service_api_shipping_method_api_service__["a" /* default */] }, { name: 'countryService', provider: __WEBPACK_IMPORTED_MODULE_12_src_core_service_api_country_api_service__["a" /* default */] }, { name: 'currencyService', provider: __WEBPACK_IMPORTED_MODULE_13_src_core_service_api_currency_api_service__["a" /* default */] }, { name: 'taxService', provider: __WEBPACK_IMPORTED_MODULE_14_src_core_service_api_tax_api_service__["a" /* default */] }, { name: 'mediaService', provider: __WEBPACK_IMPORTED_MODULE_15_src_core_service_api_media_api_service__["a" /* default */] }]);

/***/ }),
/* 399 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var ShopApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(ShopApiService, _ApiService);

    function ShopApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'shop';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, ShopApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (ShopApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(ShopApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return ShopApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (ShopApiService);

/***/ }),
/* 400 */,
/* 401 */,
/* 402 */,
/* 403 */,
/* 404 */,
/* 405 */,
/* 406 */,
/* 407 */,
/* 408 */,
/* 409 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var CategoryApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(CategoryApiService, _ApiService);

    function CategoryApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'category';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, CategoryApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (CategoryApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(CategoryApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return CategoryApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (CategoryApiService);

/***/ }),
/* 410 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var ProductApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(ProductApiService, _ApiService);

    function ProductApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'product';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, ProductApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (ProductApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(ProductApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return ProductApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (ProductApiService);

/***/ }),
/* 411 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var ProductManufacturerApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(ProductManufacturerApiService, _ApiService);

    function ProductManufacturerApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'productManufacturer';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, ProductManufacturerApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (ProductManufacturerApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(ProductManufacturerApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return ProductManufacturerApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (ProductManufacturerApiService);

/***/ }),
/* 412 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var OrderApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(OrderApiService, _ApiService);

    function OrderApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'order';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, OrderApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (OrderApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(OrderApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return OrderApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (OrderApiService);

/***/ }),
/* 413 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__ = __webpack_require__(67);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise__ = __webpack_require__(43);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass__ = __webpack_require__(32);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7__api_service__ = __webpack_require__(6);









var OrderLineItemApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits___default()(OrderLineItemApiService, _ApiService);

    function OrderLineItemApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'orderLineItem';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck___default()(this, OrderLineItemApiService);

        return __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn___default()(this, (OrderLineItemApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of___default()(OrderLineItemApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass___default()(OrderLineItemApiService, [{
        key: 'getList',
        value: function getList() {
            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 0;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 25;
            var orderUuid = arguments[2];

            if (!orderUuid) {
                return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise___default.a.reject(new Error('Missing required argument: orderUuid'));
            }

            var queryString = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default()({
                type: 'nested',
                queries: [{
                    type: 'term',
                    field: 'order_line_item.order_uuid',
                    value: orderUuid
                }]
            });

            return this.httpClient.get(this.getApiBasePath() + '?offset=' + offset + '&limit=' + limit + '&query=' + queryString).then(function (response) {
                return response.data;
            });
        }
    }]);

    return OrderLineItemApiService;
}(__WEBPACK_IMPORTED_MODULE_7__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (OrderLineItemApiService);

/***/ }),
/* 414 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__ = __webpack_require__(67);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise__ = __webpack_require__(43);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass__ = __webpack_require__(32);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_7__api_service__ = __webpack_require__(6);









var OrderDeliveryApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_6_babel_runtime_helpers_inherits___default()(OrderDeliveryApiService, _ApiService);

    function OrderDeliveryApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'orderDelivery';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_classCallCheck___default()(this, OrderDeliveryApiService);

        return __WEBPACK_IMPORTED_MODULE_5_babel_runtime_helpers_possibleConstructorReturn___default()(this, (OrderDeliveryApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_2_babel_runtime_core_js_object_get_prototype_of___default()(OrderDeliveryApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    __WEBPACK_IMPORTED_MODULE_4_babel_runtime_helpers_createClass___default()(OrderDeliveryApiService, [{
        key: 'getList',
        value: function getList() {
            var offset = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 0;
            var limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 25;
            var orderUuid = arguments[2];

            if (!orderUuid) {
                return __WEBPACK_IMPORTED_MODULE_1_babel_runtime_core_js_promise___default.a.reject(new Error('Missing required argument: orderUuid'));
            }

            var queryString = __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_json_stringify___default()({
                type: 'nested',
                queries: [{
                    type: 'term',
                    field: 'order_delivery.order_uuid',
                    value: orderUuid
                }]
            });

            return this.httpClient.get(this.getApiBasePath() + '?offset=' + offset + '&limit=' + limit + '&query=' + queryString).then(function (response) {
                return response.data;
            });
        }
    }]);

    return OrderDeliveryApiService;
}(__WEBPACK_IMPORTED_MODULE_7__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (OrderDeliveryApiService);

/***/ }),
/* 415 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var OrderStateApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(OrderStateApiService, _ApiService);

    function OrderStateApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'orderState';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, OrderStateApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (OrderStateApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(OrderStateApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return OrderStateApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (OrderStateApiService);

/***/ }),
/* 416 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var CustomerApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(CustomerApiService, _ApiService);

    function CustomerApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'customer';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, CustomerApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (CustomerApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(CustomerApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return CustomerApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (CustomerApiService);

/***/ }),
/* 417 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var CustomerGroupApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(CustomerGroupApiService, _ApiService);

    function CustomerGroupApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'customerGroup';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, CustomerGroupApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (CustomerGroupApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(CustomerGroupApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return CustomerGroupApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (CustomerGroupApiService);

/***/ }),
/* 418 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var PaymentMethodApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(PaymentMethodApiService, _ApiService);

    function PaymentMethodApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'paymentMethod';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, PaymentMethodApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (PaymentMethodApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(PaymentMethodApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return PaymentMethodApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (PaymentMethodApiService);

/***/ }),
/* 419 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var ShippingMethodApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(ShippingMethodApiService, _ApiService);

    function ShippingMethodApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'shippingMethod';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, ShippingMethodApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (ShippingMethodApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(ShippingMethodApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return ShippingMethodApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (ShippingMethodApiService);

/***/ }),
/* 420 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var CountryApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(CountryApiService, _ApiService);

    function CountryApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'areaCountry';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, CountryApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (CountryApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(CountryApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return CountryApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (CountryApiService);

/***/ }),
/* 421 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var CurrencyApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(CurrencyApiService, _ApiService);

    function CurrencyApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'currency';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, CurrencyApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (CurrencyApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(CurrencyApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return CurrencyApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (CurrencyApiService);

/***/ }),
/* 422 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var TaxApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(TaxApiService, _ApiService);

    function TaxApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'tax';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, TaxApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (TaxApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(TaxApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return TaxApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (TaxApiService);

/***/ }),
/* 423 */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__ = __webpack_require__(1);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__ = __webpack_require__(5);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_4__api_service__ = __webpack_require__(6);






var MediaApiService = function (_ApiService) {
    __WEBPACK_IMPORTED_MODULE_3_babel_runtime_helpers_inherits___default()(MediaApiService, _ApiService);

    function MediaApiService(httpClient) {
        var apiEndpoint = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'media';
        var returnFormat = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'json';

        __WEBPACK_IMPORTED_MODULE_1_babel_runtime_helpers_classCallCheck___default()(this, MediaApiService);

        return __WEBPACK_IMPORTED_MODULE_2_babel_runtime_helpers_possibleConstructorReturn___default()(this, (MediaApiService.__proto__ || __WEBPACK_IMPORTED_MODULE_0_babel_runtime_core_js_object_get_prototype_of___default()(MediaApiService)).call(this, httpClient, apiEndpoint, returnFormat));
    }

    return MediaApiService;
}(__WEBPACK_IMPORTED_MODULE_4__api_service__["a" /* default */]);

/* harmony default export */ __webpack_exports__["a"] = (MediaApiService);

/***/ }),
/* 424 */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ })
],[269]);
//# sourceMappingURL=app.js.map