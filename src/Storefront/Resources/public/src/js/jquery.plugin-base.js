;(function ($) {
    /*! Tiny Pub/Sub - v0.7.0 - 2013-01-29
     * https://github.com/cowboy/jquery-tiny-pubsub
     * Copyright (c) 2014 "Cowboy" Ben Alman; Licensed MIT */
    var o = $({});
    $.subscribe = function () {
        o.on.apply(o, arguments);
    };

    $.unsubscribe = function () {
        o.off.apply(o, arguments);
    };

    $.publish = function () {
        o.trigger.apply(o, arguments);
    };
}(jQuery));

;(function ($, window) {
    'use strict';

    var numberRegex = /^-?\d*\.?\d*$/,
        objectRegex = /^[[{]/;

    /**
     * Tries to deserialize the given string value and returns the right
     * value if its successful.
     *
     * @private
     * @method deserializeValue
     * @param {String} value
     * @returns {String|Boolean|Number|Object|Array|null}
     */
    function deserializeValue(value) {
        try {
            return !value ? value : value === 'true' || (
                value === 'false' ? false
                    : value === 'null' ? null
                    : numberRegex.test(value) ? +value
                    : objectRegex.test(value) ? $.parseJSON(value)
                    : value
            );
        } catch (e) {
            return value;
        }
    }

    /**
     * Constructor method of the PluginBase class. This method will try to
     * call the ```init```-method, where you can place your custom initialization of the plugin.
     *
     * @class PluginBase
     * @constructor
     * @param {String} name - Plugin name that is used for the events suffixes.
     * @param {HTMLElement} element - Element which should be used for the plugin.
     * @param {Object} options - The user settings, which overrides the default settings
     */
    function PluginBase(name, element, options) {
        var me = this;

        /**
         * @property {String} _name - Name of the Plugin
         * @private
         */
        me._name = name;

        /**
         * @property {jQuery} $el - Plugin element wrapped by jQuery
         */
        me.$el = $(element);

        /**
         * @property {Object} opts - Merged plugin options
         */
        me.opts = $.extend({}, me.defaults || {}, options);

        /**
         * @property {string} eventSuffix - Suffix which will be appended to the eventType to get namespaced events
         */
        me.eventSuffix = '.' + name;

        /**
         * @property {Array} _events Registered events listeners. See {@link PluginBase._on} for registration
         * @private
         */
        me._events = [];

        // Create new selector for the plugin
        $.expr[':']['plugin-' + name.toLowerCase()] = function (elem) {
            return !!$.data(elem, 'plugin_' + name);
        };

        // Call the init method of the plugin
        me.init();

        $.publish('plugin/' + name + '/onInit', [ me ]);
    }

    PluginBase.prototype = {

        /**
         * Template function for the plugin initialisation.
         * Must be overridden for custom initialisation logic or an error will be thrown.
         *
         * @public
         * @method init
         */
        init: function () {
            throw new Error('Plugin ' + this.getName() + ' has to have an init function!');
        },

        /**
         * Template function for the plugin destruction.
         * Should be overridden for custom destruction code.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('Plugin ' + this.getName() + ' should have a custom destroy method!');
            }

            this._destroy();
        },

        /**
         * Template function to update the plugin.
         * This function will be called when the breakpoint has changed but the configurations are the same.
         *
         * @public
         * @method update
         */
        update: function () {

        },

        /**
         * Destroys the plugin on the {@link HTMLElement}. It removes the instance of the plugin
         * which is bounded to the {@link jQuery} element.
         *
         * If the plugin author has used the {@link PluginBase._on} method, the added event listeners
         * will automatically be cleared.
         *
         * @private
         * @method _destroy
         * @returns {PluginBase}
         */
        _destroy: function () {
            var me = this,
                name = me.getName();

            $.each(me._events, function (i, obj) {
                if (typeof obj === 'object') {
                    obj.el.off(obj.event);
                }
            });

            // remove all references of external plugins
            $.each(me.opts, function (o) {
                delete me.opts[o];
            });

            me.$el.removeData('plugin_' + name);

            $.publish('plugin/' + name + '/onDestroy', [ me ]);

            return me;
        },

        /**
         * Wrapper method for {@link jQuery.on}, which registers in the event in the {@link PluginBase._events} array,
         * so the listeners can automatically be removed using the {@link PluginBase._destroy} method.
         *
         * @params {jQuery} Element, which should be used to add the listener
         * @params {String} Event type, you want to register.
         * @returns {PluginBase}
         */
        _on: function () {
            var me = this,
                $el = $(arguments[0]),
                event = me.getEventName(arguments[1]),
                args = Array.prototype.slice.call(arguments, 2);

            me._events.push({ 'el': $el, 'event': event });
            args.unshift(event);
            $el.on.apply($el, args);

            $.publish('plugin/' + me._name + '/onRegisterEvent', [ $el, event ]);

            return me;
        },

        /**
         * Wrapper method for {@link jQuery.off}, which removes the event listener from the {@link PluginBase._events}
         * array.
         *
         * @param {jQuery} element - Element, which contains the listener
         * @param {String} event - Name of the event to remove.
         * @returns {PluginBase}
         * @private
         */
        _off: function (element, event) {
            var me = this,
                events = me._events,
                pluginEvent = me.getEventName(event),
                eventIds = [],
                $element = $(element),
                filteredEvents = $.grep(events, function (obj, index) {
                    eventIds.push(index);
                    return typeof obj !== 'undefined' && pluginEvent === obj.event && $element[0] === obj.el[0];
                });

            $.each(filteredEvents, function (index, event) {
                $element.off(event.event);
            });

            $.each(eventIds, function (id) {
                if (!events[id]) {
                    return;
                }
                delete events[id];
            });

            $.publish('plugin/' + me._name + '/onRemoveEvent', [ $element, event ]);

            return me;
        },

        /**
         * Returns the name of the plugin.
         * @returns {PluginBase._name|String}
         */
        getName: function () {
            return this._name;
        },

        /**
         * Returns the event name with the event suffix appended.
         * @param {String} event - Event name
         * @returns {String}
         */
        getEventName: function (event) {
            var suffix = this.eventSuffix,
                parts = event.split(' '),
                len = parts.length,
                i = 0;

            for (; i < len; i++) {
                parts[i] += suffix;
            }

            return parts.join(' ');
        },

        /**
         * Returns the element which registered the plugin.
         * @returns {PluginBase.$el}
         */
        getElement: function () {
            return this.$el;
        },

        /**
         * Returns the options of the plugin. The method returns a copy of the options object and not a reference.
         * @returns {Object}
         */
        getOptions: function () {
            return $.extend({}, this.opts);
        },

        /**
         * Returns the value of a single option.
         * @param {String} key - Option key.
         * @returns {mixed}
         */
        getOption: function (key) {
            return this.opts[key];
        },

        /**
         * Sets a plugin option. Deep linking of the options are now supported.
         * @param {String} key - Option key
         * @param {mixed} value - Option value
         * @returns {PluginBase}
         */
        setOption: function (key, value) {
            var me = this;

            me.opts[key] = value;

            return me;
        },

        /**
         * Fetches the configured options based on the {@link PluginBase.$el}.
         *
         * @param {Boolean} shouldDeserialize
         * @returns {mixed} configuration
         */
        applyDataAttributes: function (shouldDeserialize) {
            var me = this, attr;

            $.each(me.opts, function (key) {
                attr = me.$el.attr('data-' + key);

                if (typeof attr === 'undefined') {
                    return true;
                }

                me.opts[key] = shouldDeserialize !== false ? deserializeValue(attr) : attr;

                return true;
            });

            $.publish('plugin/' + me._name + '/onDataAttributes', [ me.$el, me.opts ]);

            return me.opts;
        }
    };

    // Expose the private PluginBase constructor to global jQuery object
    $.PluginBase = PluginBase;

    // Object.create support test, and fallback for browsers without it
    if (typeof Object.create !== 'function') {
        Object.create = function (o) {
            function F() { }
            F.prototype = o;
            return new F();
        };
    }

    /**
     * Creates a new jQuery plugin based on the {@link PluginBase} object prototype. The plugin will
     * automatically created in {@link jQuery.fn} namespace and will initialized on the fly.
     *
     * The {@link PluginBase} object supports an automatically destruction of the registered events. To
     * do so, please use the {@link PluginBase._on} method to create event listeners.
     *
     * @param {String} name - Name of the plugin
     * @param {Object|Function} plugin - Plugin implementation
     * @returns {void}
     *
     * @example
     * // Register your plugin
     * $.plugin('yourName', {
     *    defaults: { key: 'value' },
     *
     *    init: function() {
     *        // ...initialization code
     *    },
     *
     *    destroy: function() {
     *      // ...your destruction code
     *
     *      // Use the force! Use the internal destroy method.
     *      me._destroy();
     *    }
     * });
     *
     * // Call the plugin
     * $('.test').yourName();
     */
    $.plugin = function (name, plugin) {
        var pluginFn = function (options) {
            return this.each(function () {
                var element = this,
                    pluginData = $.data(element, 'plugin_' + name);

                if (!pluginData) {
                    if (typeof plugin === 'function') {
                        /* eslint new-cap: "off" */
                        pluginData = new plugin();
                    } else {
                        var Plugin = function () {
                            PluginBase.call(this, name, element, options);
                        };

                        Plugin.prototype = $.extend(Object.create(PluginBase.prototype), { constructor: Plugin }, plugin);
                        pluginData = new Plugin();
                    }

                    $.data(element, 'plugin_' + name, pluginData);
                }
            });
        };

        window.PluginsCollection = window.PluginsCollection || {};
        window.PluginsCollection[name] = plugin;

        $.fn[name] = pluginFn;
    };

    /**
     * Provides the ability to overwrite jQuery plugins which are built on top of the {@link PluginBase} class. All of
     * our jQuery plugins (or to be more technical about it, the prototypes of our plugins) are registered in the object
     * {@link window.PluginsCollection} which can be accessed from anywhere in your storefront.
     *
     * Please keep in mind that the method overwrites the plugin in jQuery's plugin namespace {@link jQuery.fn} as well,
     * but you still have the ability to access the overwritten method(s) using the ```superclass``` object property.
     *
     * @example How to overwrite the ```showResult```-method in the "search" plugin.
     * $.overridePlugin('search', {
     *    showResult: function(response) {
     *        //.. do something with the response
     *    }
     * });
     *
     * @example Call the original method without modifications
     * $.overridePlugin('search', {
     *    showResult: function(response) {
     *        this.superclass.showResult.apply(this, arguments);
     *    }
     * });
     */
    $.overridePlugin = function (pluginName, override) {
        var overridePlugin = window.PluginsCollection[pluginName];

        if (typeof overridePlugin !== 'object' || typeof override !== 'object') {
            return false;
        }

        $.fn[pluginName] = function (options) {
            return this.each(function () {
                var element = this,
                    pluginData = $.data(element, 'plugin_' + pluginName);

                if (!pluginData) {
                    var Plugin = function () {
                        PluginBase.call(this, pluginName, element, options);
                    };

                    Plugin.prototype = $.extend(Object.create(PluginBase.prototype), { constructor: Plugin, superclass: overridePlugin }, overridePlugin, override);
                    pluginData = new Plugin();

                    $.data(element, 'plugin_' + pluginName, pluginData);
                }
            });
        };
    };
})(jQuery, window);
