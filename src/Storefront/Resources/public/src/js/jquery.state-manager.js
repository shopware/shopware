/**
 * Global state manager
 *
 * The state manager helps to master different behaviors for different screen sizes.
 * It provides you with the ability to register different states that are handled
 * by breakpoints.
 *
 * Those Breakpoints are defined by entering and exiting points (in pixels)
 * based on the viewport width.
 * By entering the breakpoint range, the enter functions of the registered
 * listeners are called.
 * But when the defined points are exceeded, the registered listeners exit
 * functions will be called.
 *
 * That way you can register callbacks that will be called on entering / exiting the defined state.
 *
 * The manager provides you multiple helper methods and polyfills which help you
 * master responsive design.
 *
 * @example Initialize the StateManager
 * ```
 *     StateManager.init([{
 *         state: 'xs',
 *         enter: '0em',
 *         exit: '47.5em'
 *      }, {
 *         state: 'm',
 *         enter: '47.5em',
 *         exit: '64em'
 *      }]);
 * ```
 *
 * @example Register breakpoint listeners
 * ```
 *     StateManager.registerListener([{
 *        state: 'xs',
 *        enter: function() { console.log('onEnter'); },
 *        exit: function() { console.log('onExit'); }
 *     }]);
 * ```
 *
 * @example Wildcard support
 * ```
 *     StateManager.registerListener([{
 *         state: '*',
 *         enter: function() { console.log('onGlobalEnter'); },
 *         exit: function() { console.log('onGlobalExit'); }
 *     }]);
 * ```
 *
 * @example StateManager Events
 * In this example we are adding an event listener for the 'resize' event.
 * This event will be called independent of the original window resize event,
 * because the resize will be compared in a requestAnimationFrame loop.
 *
 * ```
 *     StateManager.on('resize', function () {
 *         console.log('onResize');
 *     });
 *
 *     StateManager.once('resize', function () {
 *         console.log('This resize event will only be called once');
 *     });
 * ```
 *
 * @example StateManager plugin support
 * In this example we register the plugin 'pluginName' on the element
 * matching the '.my-selector' selector.
 * You can also define view ports in which the plugin will be available.
 * When switching the view ports and the configuration isn't changed for
 * that state, only the 'update' function of the plugin will be called.
 *
 * ```
 *     // The plugin will be available on all view port states.
 *     // Uses the default configuration
 *
 *     StateManager.addPlugin('.my-selector', 'pluginName');
 *
 *     // The plugin will only be available for the 'xs' state.
 *     // Uses the default configuration.
 *
 *     StateManager.addPlugin('.my-selector', 'pluginName', 'xs');
 *
 *     // The plugin will only be available for the 'l' and 'xl' state.
 *     // Uses the default configuration.
 *
 *     StateManager.addPlugin('.my-selector', 'pluginName', ['l', 'xl']);
 *
 *     // The plugin will only be available for the 'xs' and 's' state.
 *     // For those two states, the passed config will be used.
 *
 *     StateManager.addPlugin('.my-selector', 'pluginName', {
 *         'configA': 'valueA',
 *         'configB': 'valueB',
 *         'configFoo': 'valueBar'
 *     }, ['xs', 's']);
 *
 *     // The plugin is available on all view port states.
 *     // We override the 'foo' config only for the 'm' state.
 *
 *     StateManager.addPlugin('.my-selector', 'pluginName', { 'foo': 'bar' })
 *                .addPlugin('.my-selector', 'pluginName', { 'foo': 'baz' }, 'm');
 * ```
 */
;(function ($, window, document) {
    'use strict';

    var $html = $('html'),
        vendorPropertyDiv = document.createElement('div'),
        vendorPrefixes = ['webkit', 'moz', 'ms', 'o'];

    /**
     * @class EventEmitter
     * @constructor
     */
    function EventEmitter() {
        var me = this;

        /**
         * @private
         * @property _events
         * @type {Object}
         */
        me._events = {};
    }

    EventEmitter.prototype = {

        constructor: EventEmitter,

        name: 'EventEmitter',

        /**
         * @public
         * @chainable
         * @method on
         * @param {String} eventName
         * @param {Function} callback
         * @param {*} context
         * @returns {EventEmitter}
         */
        on: function (eventName, callback, context) {
            var me = this,
                events = me._events || (me._events = {}),
                event = events[eventName] || (events[eventName] = []);

            event.push({
                callback: callback,
                context: context || me
            });

            return me;
        },

        /**
         * @public
         * @chainable
         * @method once
         * @param {String} eventName
         * @param {Function} callback
         * @param {*} context
         * @returns {EventEmitter}
         */
        once: function (eventName, callback, context) {
            var me = this,
                once = function () {
                    me.off(eventName, once, context);
                    callback.apply(me, arguments);
                };

            return me.on(eventName, once, context);
        },

        /**
         * @public
         * @chainable
         * @method off
         * @param {String} eventName
         * @param {Function} callback
         * @param {*} context
         * @returns {EventEmitter}
         */
        off: function (eventName, callback, context) {
            var me = this,
                events = me._events || (me._events = {}),
                eventNames = eventName ? [eventName] : Object.keys(events),
                eventList,
                event,
                name,
                len,
                i, j;

            for (i = 0, len = eventNames.length; i < len; i++) {
                name = eventNames[i];
                eventList = events[name];

                /**
                 * Return instead of continue because only the one passed
                 * event name can be wrong / not available.
                 */
                if (!eventList) {
                    return me;
                }

                if (!callback && !context) {
                    eventList.length = 0;
                    delete events[name];
                    continue;
                }

                for (j = eventList.length - 1; j >= 0; j--) {
                    event = eventList[j];

                    // Check if the callback and the context (if passed) is the same
                    if ((callback && callback !== event.callback) || (context && context !== event.context)) {
                        continue;
                    }

                    eventList.splice(j, 1);
                }
            }

            return me;
        },

        /**
         * @public
         * @chainable
         * @method trigger
         * @param {String} eventName
         * @returns {EventEmitter}
         */
        trigger: function (eventName) {
            var me = this,
                events = me._events || (me._events = {}),
                eventList = events[eventName],
                event,
                args,
                a1, a2, a3,
                len, i;

            if (!eventList) {
                return me;
            }

            args = Array.prototype.slice.call(arguments, 1);
            len = eventList.length;
            i = -1;

            if (args.length <= 3) {
                a1 = args[0];
                a2 = args[1];
                a3 = args[2];
            }

            /**
             * Using switch to improve the performance of listener calls
             * .call() has a much greater performance than .apply() on
             * many parameters.
             */
            switch (args.length) {
            case 0:
                while (++i < len) (event = eventList[i]).callback.call(event.context);
                return me;
            case 1:
                while (++i < len) (event = eventList[i]).callback.call(event.context, a1);
                return me;
            case 2:
                while (++i < len) (event = eventList[i]).callback.call(event.context, a1, a2);
                return me;
            case 3:
                while (++i < len) (event = eventList[i]).callback.call(event.context, a1, a2, a3);
                return me;
            default:
                while (++i < len) (event = eventList[i]).callback.apply(event.context, args);
                return me;
            }
        },

        /**
         * @public
         * @method destroy
         */
        destroy: function () {
            this.off();
        }
    };

    /**
     * @public
     * @static
     * @class StateManager
     * @extends {EventEmitter}
     * @type {Object}
     */
    window.StateManager = $.extend(Object.create(EventEmitter.prototype), {

        /**
         * Collection of all registered breakpoints
         *
         * @private
         * @property _breakpoints
         * @type {Array}
         */
        _breakpoints: [],

        /**
         * Collection of all registered listeners
         *
         * @private
         * @property _listeners
         * @type {Array}
         */
        _listeners: [],

        /**
         * Collection of all added plugin configurations
         *
         * @private
         * @property _plugins
         * @type {Object}
         */
        _plugins: {},

        /**
         * Collection of all plugins that should be initialized when the DOM is ready
         *
         * @private
         * @property _pluginQueue
         * @type {Object}
         */
        _pluginQueue: {},

        /**
         * Flag whether the queued plugins were initialized or not
         *
         * @private
         * @property _pluginsInitialized
         * @type {Boolean}
         */
        _pluginsInitialized: false,

        /**
         * Current breakpoint type
         *
         * @private
         * @property _currentState
         * @type {String}
         */
        _currentState: '',

        /**
         * Previous breakpoint type
         *
         * @private
         * @property _previousState
         * @type {String}
         */
        _previousState: '',

        /**
         * Last calculated viewport width.
         *
         * @private
         * @property _viewportWidth
         * @type {Number}
         */
        _viewportWidth: 0,

        /**
         * Cache for all previous gathered vendor properties.
         *
         * @private
         * @property _vendorPropertyCache
         * @type {Object}
         */
        _vendorPropertyCache: {},

        /**
         * Initializes the StateManager with the incoming breakpoint
         * declaration and starts the listing of the resize of the browser window.
         *
         * @public
         * @chainable
         * @method init
         * @param {Object|Array} breakpoints - User defined breakpoints.
         * @returns {StateManager}
         */
        init: function (breakpoints) {
            var me = this;

            me._viewportWidth = me.getViewportWidth();

            me._baseFontSize = parseInt($html.css('font-size'));

            me.registerBreakpoint(breakpoints);

            me._checkResize();
            me._browserDetection();
            me._setDeviceCookie();
            $($.proxy(me.initQueuedPlugins, me, true));
            $.publish('StateManager/onInit', [ me ]);
            return me;
        },

        /**
         * Adds a breakpoint to check against, after the {@link StateManager.init} was called.
         *
         * @public
         * @chainable
         * @method registerBreakpoint
         * @param {Array|Object} breakpoint.
         * @returns {StateManager}
         */
        registerBreakpoint: function (breakpoint) {
            var me = this,
                breakpoints = breakpoint instanceof Array ? breakpoint : Array.prototype.slice.call(arguments),
                len = breakpoints.length,
                i = 0;

            for (; i < len; i++) {
                me._addBreakpoint(breakpoints[i]);
            }

            return me;
        },

        /**
         * Adds a breakpoint to check against, after the {@link StateManager.init} was called.
         *
         * @private
         * @chainable
         * @method _addBreakpoint
         * @param {Object} breakpoint.
         */
        _addBreakpoint: function (breakpoint) {
            var me = this,
                breakpoints = me._breakpoints,
                existingBreakpoint,
                state = breakpoint.state,
                enter = me._convertRemValue(breakpoint.enter),
                exit = me._convertRemValue(breakpoint.exit),
                len = breakpoints.length,
                i = 0;

            breakpoint.enter = enter;
            breakpoint.exit = exit;

            for (; i < len; i++) {
                existingBreakpoint = breakpoints[i];

                if (existingBreakpoint.state === state) {
                    throw new Error('Multiple breakpoints of state "' + state + '" detected.');
                }

                if (existingBreakpoint.enter <= exit && enter <= existingBreakpoint.exit) {
                    throw new Error('Breakpoint range of state "' + state + '" overlaps state "' + existingBreakpoint.state + '".');
                }
            }

            breakpoints.push(breakpoint);

            me._plugins[state] = {};
            me._checkBreakpoint(breakpoint, me._viewportWidth);

            return me;
        },

        _convertRemValue: function(remValue) {
            var me = this,
                baseFontSize = me._baseFontSize;

            return remValue * baseFontSize;
        },

        /**
         * Removes breakpoint by state and removes the generated getter method for the state.
         *
         * @public
         * @chainable
         * @method removeBreakpoint
         * @param {String} state State which should be removed
         * @returns {StateManager}
         */
        removeBreakpoint: function (state) {
            var me = this,
                breakpoints = me._breakpoints,
                len = breakpoints.length,
                i = 0;

            if (typeof state !== 'string') {
                return me;
            }

            for (; i < len; i++) {
                if (state !== breakpoints[i].state) {
                    continue;
                }

                breakpoints.splice(i, 1);

                return me._removeStatePlugins(state);
            }

            return me;
        },

        /**
         * @protected
         * @chainable
         * @method _removeStatePlugins
         * @param {String} state
         * @returns {StateManager}
         */
        _removeStatePlugins: function (state) {
            var me = this,
                plugins = me._plugins[state],
                selectors = Object.keys(plugins),
                selectorLen = selectors.length,
                pluginNames,
                pluginLen,
                i, j;

            for (i = 0; i < selectorLen; i++) {
                pluginNames = Object.keys(plugins[selectors[i]]);

                for (j = 0, pluginLen = pluginNames.length; j < pluginLen; j++) {
                    me.destroyPlugin(selectors[i], pluginNames[j]);
                }
            }

            delete plugins[state];

            return me;
        },

        /**
         * Registers one or multiple event listeners to the StateManager,
         * so they will be fired when the state matches the current active
         * state..
         *
         * @public
         * @chainable
         * @method registerListener
         * @param {Object|Array} listener
         * @returns {StateManager}
         */
        registerListener: function (listener) {
            var me = this,
                listenerArr = listener instanceof Array ? listener : Array.prototype.slice.call(arguments),
                len = listenerArr.length,
                i = 0;

            for (; i < len; i++) {
                me._addListener(listenerArr[i]);
            }

            return me;
        },

        /**
         * @private
         * @chainable
         * @method _addListener
         * @param {Object} listener.
         */
        _addListener: function (listener) {
            var me = this,
                listeners = me._listeners,
                enterFn = listener.enter;

            listeners.push(listener);

            if ((listener.state === me._currentState || listener.state === '*') && typeof enterFn === 'function') {
                enterFn({
                    'exiting': me._previousState,
                    'entering': me._currentState
                });
            }

            return me;
        },

        /**
         * @public
         * @chainable
         * @method addPlugin
         * @param {String} selector
         * @param {String} pluginName
         * @param {Object|Array|String} config
         * @param {Array|String} viewport
         * @returns {StateManager}
         */
        addPlugin: function (selector, pluginName, config, viewport) {
            var me = this,
                pluginsInitialized = me._pluginsInitialized,
                breakpoints = me._breakpoints,
                currentState = me._currentState,
                len,
                i;

            // If the third parameter are the viewport states
            if (typeof config === 'string' || config instanceof Array) {
                viewport = config;
                config = {};
            }

            if (typeof viewport === 'string') {
                viewport = [viewport];
            }

            if (!(viewport instanceof Array)) {
                viewport = [];

                for (i = 0, len = breakpoints.length; i < len; i++) {
                    viewport.push(breakpoints[i].state);
                }
            }

            for (i = 0, len = viewport.length; i < len; i++) {
                me._addPluginOption(viewport[i], selector, pluginName, config);

                if (currentState !== viewport[i]) {
                    continue;
                }

                if (pluginsInitialized) {
                    me._initPlugin(selector, pluginName);
                    continue;
                }

                me.addPluginToQueue(selector, pluginName);
            }

            return me;
        },

        /**
         * @public
         * @chainable
         * @method removePlugin
         * @param {String} selector
         * @param {String} pluginName
         * @param {Array|String} viewport
         * @returns {StateManager}
         */
        removePlugin: function (selector, pluginName, viewport) {
            var me = this,
                breakpoints = me._breakpoints,
                plugins = me._plugins,
                state,
                sel,
                len,
                i;

            if (typeof viewport === 'string') {
                viewport = [viewport];
            }

            if (!(viewport instanceof Array)) {
                viewport = [];

                for (i = 0, len = breakpoints.length; i < len; i++) {
                    viewport.push(breakpoints[i].state);
                }
            }

            for (i = 0, len = viewport.length; i < len; i++) {
                if (!(state = plugins[viewport[i]])) {
                    continue;
                }

                if (!(sel = state[selector])) {
                    continue;
                }

                delete sel[pluginName];
            }

            if (!me._pluginsInitialized) {
                me.removePluginFromQueue(selector, pluginName);
            }

            return me;
        },

        /**
         * @public
         * @chainable
         * @method updatePlugin
         * @param {String} selector
         * @param {String} pluginName
         * @returns {StateManager}
         */
        updatePlugin: function (selector, pluginName) {
            var me = this,
                state = me._currentState,
                pluginConfigs = me._plugins[state][selector] || {},
                pluginNames = (typeof pluginName === 'string') ? [pluginName] : Object.keys(pluginConfigs),
                len = pluginNames.length,
                i = 0;

            for (; i < len; i++) {
                me._initPlugin(selector, pluginNames[i]);
            }

            return me;
        },

        /**
         * @private
         * @method _addPluginOption
         * @param {String} state
         * @param {String} selector
         * @param {String} pluginName
         * @param {Object} config
         */
        _addPluginOption: function (state, selector, pluginName, config) {
            var me = this,
                plugins = me._plugins,
                selectors = plugins[state] || (plugins[state] = {}),
                configs = selectors[selector] || (selectors[selector] = {}),
                pluginConfig = configs[pluginName];

            configs[pluginName] = $.extend(pluginConfig || {}, config);
        },

        /**
         * @private
         * @method _initPlugin
         * @param {String} selector
         * @param {String} pluginName
         */
        _initPlugin: function (selector, pluginName) {
            var me = this,
                $el = $(selector);

            if ($el.length > 1) {
                $.each($el, function () {
                    me._initSinglePlugin($(this), selector, pluginName);
                });
                return;
            }

            me._initSinglePlugin($el, selector, pluginName);
        },

        /**
         * @public
         * @method addPluginToQueue
         * @param {String} selector
         * @param {String} pluginName
         */
        addPluginToQueue: function (selector, pluginName) {
            var me = this,
                queue = me._pluginQueue,
                pluginNames = queue[selector] || (queue[selector] = []);

            if (pluginNames.indexOf(pluginName) === -1) {
                pluginNames.push(pluginName);
            }
        },

        /**
         * @public
         * @method removePluginFromQueue
         * @param {String} selector
         * @param {String} pluginName
         */
        removePluginFromQueue: function (selector, pluginName) {
            var me = this,
                queue = me._pluginQueue,
                pluginNames = queue[selector],
                index;

            if (pluginNames && (index = pluginNames.indexOf(pluginName)) !== -1) {
                pluginNames.splice(index, 1);
            }
        },

        /**
         * @public
         * @method initQueuedPlugins
         * @param {Boolean} clearQueue
         */
        initQueuedPlugins: function (clearQueue) {
            var me = this,
                queue = me._pluginQueue,
                selectors = Object.keys(queue),
                selectorLen = selectors.length,
                i = 0,
                selector,
                plugins,
                pluginLen,
                j;

            for (; i < selectorLen; i++) {
                selector = selectors[i];
                plugins = queue[selector];

                for (j = 0, pluginLen = plugins.length; j < pluginLen; j++) {
                    me._initPlugin(selector, plugins[j]);
                }

                if (clearQueue !== false) {
                    delete queue[selector];
                }
            }

            me._pluginsInitialized = true;
        },

        /**
         * @private
         * @method _initSinglePlugin
         * @param {Object} element
         * @param {String} selector
         * @param {String} pluginName
         */
        _initSinglePlugin: function (element, selector, pluginName) {
            var me = this,
                currentConfig = me._getPluginConfig(me._currentState, selector, pluginName),
                plugin = element.data('plugin_' + pluginName);

            if (!plugin) {
                if (!element[pluginName]) {
                    console.error('Plugin "' + pluginName + '" is not a valid jQuery-plugin!');
                    return;
                }

                element[pluginName](currentConfig);
                return;
            }

            if (JSON.stringify(currentConfig) === JSON.stringify(me._getPluginConfig(me._previousState, selector, pluginName))) {
                if (typeof plugin.update === 'function') {
                    plugin.update(me._currentState, me._previousState);
                }
                return;
            }

            me.destroyPlugin(element, pluginName);

            element[pluginName](currentConfig);
        },

        /**
         * @private
         * @method _getPluginConfig
         * @param {String} state
         * @param {String} selector
         * @param {String} plugin
         */
        _getPluginConfig: function (state, selector, plugin) {
            var selectors = this._plugins[state] || {},
                pluginConfigs = selectors[selector] || {};

            return pluginConfigs[plugin] || {};
        },

        /**
         * @private
         * @method _checkResize
         */
        _checkResize: function () {
            var me = this,
                width = me.getViewportWidth();

            if (width !== me._viewportWidth) {
                me._checkBreakpoints(width);
                me.trigger('resize', width);
                me._setDeviceCookie();
            }

            me._viewportWidth = width;

            me.requestAnimationFrame(me._checkResize.bind(me));
        },

        /**
         * @private
         * @method _checkBreakpoints
         * @param {Number} width
         */
        _checkBreakpoints: function (width) {
            var me = this,
                checkWidth = width || me.getViewportWidth(),
                breakpoints = me._breakpoints,
                len = breakpoints.length,
                i = 0;

            for (; i < len; i++) {
                me._checkBreakpoint(breakpoints[i], checkWidth);
            }

            return me;
        },

        /**
         * @private
         * @method _checkBreakpoint
         * @param {Object} breakpoint
         * @param {Number} width
         */
        _checkBreakpoint: function (breakpoint, width) {
            var me = this,
                checkWidth = width || me.getViewportWidth(),
                enterWidth = ~~(breakpoint.enter),
                exitWidth = ~~(breakpoint.exit),
                state = breakpoint.state;

            if (state !== me._currentState && checkWidth >= enterWidth && checkWidth <= exitWidth) {
                me._changeBreakpoint(state);
            }
        },

        /**
         * @private
         * @chainable
         * @method _changeBreakpoint
         * @param {String} state
         * @returns {StateManager}
         */
        _changeBreakpoint: function (state) {
            var me = this,
                previousState = me._previousState = me._currentState,
                currentState = me._currentState = state;

            return me
                .trigger('exitBreakpoint', previousState)
                .trigger('changeBreakpoint', {
                    'entering': currentState,
                    'exiting': previousState
                })
                .trigger('enterBreakpoint', currentState)
                ._switchListener(previousState, currentState)
                ._switchPlugins(previousState, currentState);
        },

        /**
         * @private
         * @chainable
         * @method _switchListener
         * @param {String} fromState
         * @param {String} toState
         * @returns {StateManager}
         */
        _switchListener: function (fromState, toState) {
            var me = this,
                previousListeners = me._getBreakpointListeners(fromState),
                currentListeners = me._getBreakpointListeners(toState),
                eventObj = {
                    'exiting': fromState,
                    'entering': toState
                },
                callFn,
                len,
                i;

            for (i = 0, len = previousListeners.length; i < len; i++) {
                if (typeof (callFn = previousListeners[i].exit) === 'function') {
                    callFn(eventObj);
                }
            }

            for (i = 0, len = currentListeners.length; i < len; i++) {
                if (typeof (callFn = currentListeners[i].enter) === 'function') {
                    callFn(eventObj);
                }
            }

            return me;
        },

        /**
         * @private
         * @method _getBreakpointListeners
         * @param {String} state
         * @returns {Array}
         */
        _getBreakpointListeners: function (state) {
            var me = this,
                listeners = me._listeners,
                breakpointListeners = [],
                len = listeners.length,
                i = 0,
                listenerType;

            for (; i < len; i++) {
                if ((listenerType = listeners[i].state) === state || listenerType === '*') {
                    breakpointListeners.push(listeners[i]);
                }
            }

            return breakpointListeners;
        },

        /**
         * @private
         * @chainable
         * @method _switchPlugins
         * @param {String} fromState
         * @param {String} toState
         * @returns {StateManager}
         */
        _switchPlugins: function (fromState, toState) {
            var me = this,
                plugins = me._plugins,
                fromSelectors = plugins[fromState] || {},
                fromKeys = Object.keys(fromSelectors),
                selector,
                oldPluginConfigs,
                newPluginConfigs,
                configKeys,
                pluginName,
                plugin,
                $el,
                toSelectors = plugins[toState] || {},
                toKeys = Object.keys(toSelectors),
                lenKeys, lenConfig, lenEl,
                x, y, z;

            for (x = 0, lenKeys = fromKeys.length; x < lenKeys; x++) {
                selector = fromKeys[x];
                oldPluginConfigs = fromSelectors[selector];
                $el = $(selector);

                if (!oldPluginConfigs || !(lenEl = $el.length)) {
                    continue;
                }

                newPluginConfigs = toSelectors[selector];
                configKeys = Object.keys(oldPluginConfigs);

                for (y = 0, lenConfig = configKeys.length; y < lenConfig; y++) {
                    pluginName = configKeys[y];

                    // When no new state config is available, destroy the old plugin
                    if (!newPluginConfigs || !(newPluginConfigs[pluginName])) {
                        me.destroyPlugin($el, pluginName);
                        continue;
                    }

                    if (JSON.stringify(newPluginConfigs[pluginName]) === JSON.stringify(oldPluginConfigs[pluginName])) {
                        for (z = 0; z < lenEl; z++) {
                            if (!(plugin = $($el[z]).data('plugin_' + pluginName))) {
                                continue;
                            }

                            if (typeof plugin.update === 'function') {
                                plugin.update(fromState, toState);
                            }
                        }
                        continue;
                    }

                    me.destroyPlugin($el, pluginName);
                }
            }

            for (x = 0, lenKeys = toKeys.length; x < lenKeys; x++) {
                selector = toKeys[x];
                newPluginConfigs = toSelectors[selector];
                $el = $(selector);

                if (!newPluginConfigs || !$el.length) {
                    continue;
                }

                configKeys = Object.keys(newPluginConfigs);

                for (y = 0, lenConfig = configKeys.length; y < lenConfig; y++) {
                    pluginName = configKeys[y];

                    if (!$el.data('plugin_' + pluginName)) {
                        $el[pluginName](newPluginConfigs[pluginName]);
                    }
                }
            }

            return me;
        },

        /**
         * @public
         * @method destroyPlugin
         * @param {String|jQuery} selector
         * @param {String} pluginName
         */
        destroyPlugin: function (selector, pluginName) {
            var $el = (typeof selector === 'string') ? $(selector) : selector,
                name = 'plugin_' + pluginName,
                len = $el.length,
                i = 0,
                $currentEl,
                plugin;

            if (!len) {
                return;
            }

            for (; i < len; i++) {
                $currentEl = $($el[i]);

                if ((plugin = $currentEl.data(name))) {
                    plugin.destroy();
                    $currentEl.removeData(name);
                }
            }
        },

        /**
         * Returns the current viewport width.
         *
         * @public
         * @method getViewportWidth
         * @returns {Number} The width of the viewport in pixels.
         */
        getViewportWidth: function () {
            var width = window.innerWidth;

            if (typeof width === 'number') {
                return width;
            }

            return (width = document.documentElement.clientWidth) !== 0 ? width : document.body.clientWidth;
        },

        /**
         * Returns the current viewport height.
         *
         * @public
         * @method getViewportHeight
         * @returns {Number} The height of the viewport in pixels.
         */
        getViewportHeight: function () {
            var height = window.innerHeight;

            if (typeof height === 'number') {
                return height;
            }

            return (height = document.documentElement.clientHeight) !== 0 ? height : document.body.clientHeight;
        },

        /**
         * Returns the current active state.
         *
         * @public
         * @method getPrevious
         * @returns {String} previous breakpoint state
         */
        getPreviousState: function () {
            return this._previousState;
        },

        /**
         * Returns whether or not the previous active type is the passed one.
         *
         * @public
         * @method getPrevious
         * @param {String|Array} state
         * @returns {Boolean}
         */
        isPreviousState: function (state) {
            var states = state instanceof Array ? state : Array.prototype.slice.call(arguments),
                previousState = this._previousState,
                len = states.length,
                i = 0;

            for (; i < len; i++) {
                if (previousState === states[i]) {
                    return true;
                }
            }

            return false;
        },

        /**
         * Returns the current active state.
         *
         * @public
         * @method getCurrent
         * @returns {String} current breakpoint state
         */
        getCurrentState: function () {
            return this._currentState;
        },

        /**
         * Returns whether or not the current active state is the passed one.
         *
         * @public
         * @method isCurrent
         * @param {String | Array} state
         * @returns {Boolean}
         */
        isCurrentState: function (state) {
            var states = state instanceof Array ? state : Array.prototype.slice.call(arguments),
                currentState = this._currentState,
                len = states.length,
                i = 0;

            for (; i < len; i++) {
                if (currentState === states[i]) {
                    return true;
                }
            }

            return false;
        },

        /**
         * Checks if the device is currently running in portrait mode.
         *
         * @public
         * @method isPortraitMode
         * @returns {Boolean} Whether or not the device is in portrait mode
         */
        isPortraitMode: function () {
            return !!this.matchMedia('(orientation: portrait)').matches;
        },

        /**
         * Checks if the device is currently running in landscape mode.
         *
         * @public
         * @method isLandscapeMode
         * @returns {Boolean} Whether or not the device is in landscape mode
         */
        isLandscapeMode: function () {
            return !!this.matchMedia('(orientation: landscape)').matches;
        },

        /**
         * Gets the device pixel ratio. All retina displays should return a value > 1, all standard
         * displays like a desktop monitor will return 1.
         *
         * @public
         * @method getDevicePixelRatio
         * @returns {Number} The device pixel ratio.
         */
        getDevicePixelRatio: function () {
            return window.devicePixelRatio || 1;
        },

        /**
         * Returns if the current user agent is matching the browser test.
         *
         * @param browser
         * @returns {boolean}
         */
        isBrowser: function(browser) {
            var regEx = new RegExp(browser.toLowerCase(), 'i');
            return this._checkUserAgent(regEx);
        },

        /**
         * Checks the user agent against the given regexp.
         *
         * @param regEx
         * @returns {boolean}
         * @private
         */
        _checkUserAgent: function(regEx) {
            return !!navigator.userAgent.toLowerCase().match(regEx);
        },

        /**
         * Detects the browser type and adds specific css classes to the html tag.
         *
         * @private
         */
        _browserDetection: function() {
            var me = this,
                detections = {};

            detections['is--opera'] = me._checkUserAgent(/opera/);
            detections['is--chrome'] = me._checkUserAgent(/\bchrome\b/);
            detections['is--firefox'] = me._checkUserAgent(/firefox/);
            detections['is--webkit'] = me._checkUserAgent(/webkit/);
            detections['is--safari'] = !detections['is--chrome'] && me._checkUserAgent(/safari/);
            detections['is--ie'] = !detections['is--opera'] && (me._checkUserAgent(/msie/) || me._checkUserAgent(/trident\/7/));
            detections['is--ie-touch'] = detections['is--ie'] && me._checkUserAgent(/touch/);
            detections['is--gecko'] = !detections['is--webkit'] && me._checkUserAgent(/gecko/);

            $.each(detections, function(key, value) {
                if (value) $html.addClass(key);
            });
        },

        _getCurrentDevice: function() {
            var me = this,
                devices = {
                    'xs': 'mobile',
                    's': 'mobile',
                    'm': 'tablet',
                    'l': 'tablet',
                    'xl': 'desktop'
                };

            return devices[me.getCurrentState()] || 'desktop';
        },

        _setDeviceCookie: function() {
            var me = this,
                device = me._getCurrentDevice();

            document.cookie = 'x-ua-device=' + device + '; path=/';
        },

        /**
         * First calculates the scroll bar width and height of the browser
         * and saves it to a object that can be accessed.
         *
         * @private
         * @property _scrollBarSize
         * @type {Object}
         */
        _scrollBarSize: (function () {
            var $el = $('<div>', {
                    css: {
                        width: 100,
                        height: 100,
                        overflow: 'scroll',
                        position: 'absolute',
                        top: -9999
                    }
                }),
                el = $el[0],
                width,
                height;

            $('body').append($el);

            width = el.offsetWidth - el.clientWidth;
            height = el.offsetHeight - el.clientHeight;

            $($el).remove();

            return {
                width: width,
                height: height
            };
        }()),

        /**
         * Returns an object containing the width and height of the default
         * scroll bar sizes.
         *
         * @public
         * @method getScrollBarSize
         * @returns {Object} The width/height pair of the scroll bar size.
         */
        getScrollBarSize: function () {
            return $.extend({}, this._scrollBarSize);
        },

        /**
         * Returns the default scroll bar width of the browser.
         *
         * @public
         * @method getScrollBarWidth
         * @returns {Number} Width of the default browser scroll bar.
         */
        getScrollBarWidth: function () {
            return this._scrollBarSize.width;
        },

        /**
         * Returns the default scroll bar width of the browser.
         *
         * @public
         * @method getScrollBarHeight
         * @returns {Number} Height of the default browser scroll bar.
         */
        getScrollBarHeight: function () {
            return this._scrollBarSize.height;
        },

        /**
         * matchMedia() polyfill
         * Test a CSS media type/query in JS.
         * Authors & copyright (c) 2012: Scott Jehl, Paul Irish, Nicholas Zakas, David Knight.
         * Dual MIT/BSD license
         *
         * @public
         * @method matchMedia
         * @param {String} media
         */
        matchMedia: (function () {
            // For browsers that support matchMedium api such as IE 9 and webkit
            var styleMedia = (window.styleMedia || window.media);

            // For those that don't support matchMedium
            if (!styleMedia) {
                var style = document.createElement('style'),
                    script = document.getElementsByTagName('script')[0],
                    info = null;

                style.type = 'text/css';
                style.id = 'matchmediajs-test';

                script.parentNode.insertBefore(style, script);

                // 'style.currentStyle' is used by IE <= 8 and 'window.getComputedStyle' for all other browsers
                info = ('getComputedStyle' in window) && window.getComputedStyle(style, null) || style.currentStyle;

                styleMedia = {
                    matchMedium: function (media) {
                        var text = '@media ' + media + '{ #matchmediajs-test { width: 1px; } }';

                        // 'style.styleSheet' is used by IE <= 8 and 'style.textContent' for all other browsers
                        if (style.styleSheet) {
                            style.styleSheet.cssText = text;
                        } else {
                            style.textContent = text;
                        }

                        // Test if media query is true or false
                        return info.width === '1px';
                    }
                };
            }

            return function (media) {
                return {
                    matches: styleMedia.matchMedium(media || 'all'),
                    media: media || 'all'
                };
            };
        }()),

        /**
         * requestAnimationFrame() polyfill
         *
         * @public
         * @method requestAnimationFrame
         * @param {Function} callback
         * @returns {Number}
         */
        requestAnimationFrame: (function () {
            var raf = window.requestAnimationFrame,
                i = vendorPrefixes.length,
                lastTime = 0;

            while (!raf && i) {
                raf = window[vendorPrefixes[i--] + 'RequestAnimationFrame'];
            }

            return raf || function (callback) {
                var currTime = +(new Date()),
                    timeToCall = Math.max(0, 16 - (currTime - lastTime)),
                    id = window.setTimeout(function () {
                        callback(currTime + timeToCall);
                    }, timeToCall);

                lastTime = currTime + timeToCall;

                return id;
            };
        }()).bind(window),

        /**
         * cancelAnimationFrame() polyfill
         *
         * @public
         * @method cancelAnimationFrame
         * @param {Number} id
         */
        cancelAnimationFrame: (function () {
            var caf = window.cancelAnimationFrame,
                i = vendorPrefixes.length,
                fnName;

            while (!caf && i) {
                fnName = vendorPrefixes[i--];
                caf = window[fnName + 'CancelAnimationFrame'] || window[fnName + 'CancelRequestAnimationFrame'];
            }

            return caf || window.clearTimeout;
        }()).bind(window),

        /**
         * Tests the given CSS style property on an empty div with all vendor
         * properties. If it fails and the softError flag was not set, it
         * returns null, otherwise the given property.
         *
         * @example
         *
         * // New chrome version
         * StateManager.getVendorProperty('transform'); => 'transform'
         *
         * // IE9
         * StateManager.getVendorProperty('transform'); => 'msTransform'
         *
         * // Property not supported, without soft error flag
         * StateManager.getVendorProperty('animation'); => null
         *
         * // Property not supported, with soft error flag
         * StateManager.getVendorProperty('animation', true); => 'animate'
         *
         * @public
         * @method getVendorProperty
         * @param {String} property
         * @param {Boolean} softError
         */
        getVendorProperty: function (property, softError) {
            var cache = this._vendorPropertyCache,
                style = vendorPropertyDiv.style;

            if (cache[property]) {
                return cache[property];
            }

            if (property in style) {
                return (cache[property] = property);
            }

            var prop = property.charAt(0).toUpperCase() + property.substr(1),
                len = vendorPrefixes.length,
                i = 0,
                vendorProp;

            for (; i < len; i++) {
                vendorProp = vendorPrefixes[i] + prop;

                if (vendorProp in style) {
                    return (cache[property] = vendorProp);
                }
            }

            return (cache[property] = (softError ? property : null));
        }
    });
})(jQuery, window, document);
