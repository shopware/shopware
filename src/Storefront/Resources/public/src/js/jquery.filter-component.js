;(function($, window, document, undefined) {
    'use strict';

    /**
     * An object holding the configuration objects
     * of special component types. The specific
     * configuration objects are getting merged
     * into the original plugin for the corresponding
     * component type. This is used for special components
     * to override some of the base methods to make them
     * work properly and for firing correct change events.
     *
     * @type {}
     */
    var specialComponents = {

        'value': {
            updateFacet: function(data) {
                var me = this;

                if (me.isChecked(me.$inputs)) {
                    return;
                }
                me.disable(me.$el, data === null);
                me.disable(me.$inputs, data === null);
            }
        },

        'value-list': {
            updateFacet: function(data) {
                this.updateValueList(data);
            }
        },

        'value-list-single': {
            compOpts: {
                checkboxSelector: 'input[type="checkbox"]'
            },

            initComponent: function() {
                var me = this;

                me.$inputs = me.$el.find(me.opts.checkboxSelector);

                me.registerComponentEvents();
            },

            validateComponentShouldBeDisabled: function(data, values, checkedIds) {
                if (checkedIds.length > 0) {
                    return false;
                }
                if (values && values.length <= 0) {
                    return true;
                }
                return data == null;
            },

            registerComponentEvents: function() {
                var me = this;

                me._on(me.$inputs, 'change', function(event) {
                    var $el = $(event.currentTarget);
                    if ($el.is(':checked')) {
                        me.$inputs.not($el).attr('disabled', 'disabled').parent().addClass('is--disabled');
                    }
                    me.onChange(event);
                });
            },

            updateFacet: function(data) {
                this.updateValueList(data);
            },

            validateElementShouldBeDisabled: function($element, activeIds, ids, checkedIds, value) {
                var val = $element.val();
                if (checkedIds.length > 0) {
                    return checkedIds.indexOf(val) === -1;
                }
                if (activeIds.length > 0) {
                    return activeIds.indexOf(val) === -1;
                }
                return ids.indexOf(val) === -1;
            }
        },

        'radio': {
            compOpts: {
                radioInputSelector: 'input[type="radio"]'
            },

            initComponent: function() {
                var me = this;
                me.$radioInputs = me.$el.find(me.opts.radioInputSelector);
                me.$inputs = me.$radioInputs;
                me.registerComponentEvents();
            },

            registerComponentEvents: function() {
                var me = this;
                me._on(me.$radioInputs, 'change', function(event) {
                    me.onChange(event);
                });
            },

            updateFacet: function(data) {
                this.updateValueList(data);
            }
        },

        'value-tree': {
            updateFacet: function(data) {
                this.updateValueList(data);
            },

            getValueIds: function(values) {
                var ids = [];
                $(values).each(function(index, value) {
                    ids.push(value.id + '');
                });
                return ids;
            },

            registerComponentEvents: function() {
                var me = this;

                me._on(me.$inputs, 'change', function(event) {
                    var $el = $(event.currentTarget);
                    if ($el.is(':checked')) {
                        me.$inputs.not($el).attr('disabled', 'disabled').parent().addClass('is--disabled');
                        me.$inputs.not($el).prop('checked', false);
                    } else {
                        me.$inputs.removeAttr('disabled').parent().removeClass('is--disabled');
                    }
                    me.onChange(event);
                });
            },

            getValues: function(data, $elements) {
                return this.recursiveGetValues(data.values);
            },

            recursiveGetValues: function(values) {
                var items = [];
                var me = this;

                $(values).each(function (index, value) {
                    items.push(value);
                    if (value.values.length > 0) {
                        items = items.concat(me.recursiveGetValues(value.values));
                    }
                });
                return items;
            }
        },

        'value-tree-single': {
            updateFacet: function(data) {
                this.updateValueList(data);
            },

            registerComponentEvents: function() {
                var me = this;

                me._on(me.$inputs, 'change', function(event) {
                    var $el = $(event.currentTarget);

                    if ($el.is(':checked')) {
                        me.$inputs.not($el).attr('disabled', 'disabled').parent().addClass('is--disabled');
                        me.$inputs.not($el).prop('checked', false);
                    }
                    me.onChange(event);
                });
            },

            getValues: function(data, $elements) {
                if (!data || !data.values) {
                    return [];
                }

                return this.recursiveGetValues(data.values);
            },

            recursiveGetValues: function(values) {
                var me = this, items = [];

                $(values).each(function (index, value) {
                    value.id = value.id + '';

                    items.push(value);
                    if (value.values.length > 0) {
                        items = items.concat(me.recursiveGetValues(value.values));
                    }
                });
                return items;
            },

            validateElementShouldBeDisabled: function($element, activeIds, ids, checkedIds, value) {
                var val = $element.val();
                if (activeIds.length > 0) {
                    return activeIds.indexOf(val) === -1;
                }
                if (checkedIds.length > 0) {
                    return checkedIds.indexOf(val) === -1;
                }
                return ids.indexOf(val) === -1;
            }
        },

        /**
         * Range-Slider component
         */
        'range': {

            compOpts: {
                rangeSliderSelector: '*[data-range-slider="true"]'
            },

            initComponent: function() {
                var me = this;

                me.$rangeSliderEl = me.$el.find(me.opts.rangeSliderSelector);
                me.$rangeInputs = me.$rangeSliderEl.find('input');
                me.rangeSlider = me.$rangeSliderEl.data('plugin_swRangeSlider');
                me.registerComponentEvents();
            },

            updateFacet: function(data) {
                var me = this, initial, isFiltered;

                initial = me.rangeSlider.opts;

                isFiltered = (
                    me.rangeSlider.minValue != initial.rangeMin || me.rangeSlider.maxValue != initial.rangeMax
                );

                if (!isFiltered && data) {
                    isFiltered = data.activeMin !== data.min || data.activeMax !== data.max;
                }

                if (isFiltered) {
                    me.disableComponent(false);
                    return;
                }

                if (data === null) {
                    me.disableComponent(true);
                    return;
                }

                if (data.min == data.max) {
                    me.disableComponent(true);
                    return;
                }

                me.disableComponent(false);

                me.rangeSlider.opts.rangeMax = data.max;
                me.rangeSlider.opts.rangeMin = data.min;
                me.rangeSlider.opts.startMax = data.activeMax;
                me.rangeSlider.opts.startMin = data.activeMin;
                me.rangeSlider.computeBaseValues();
            },

            registerComponentEvents: function() {
                var me = this;
                me._on(me.$rangeInputs, 'change', $.proxy(me.onChange, me));
            }
        },

        /**
         * Rating component
         */
        'rating': {

            compOpts: {
                starInputSelector: '.filter-panel--star-rating input'
            },

            initComponent: function() {
                var me = this;

                me.$starInputs = me.$el.find(me.opts.starInputSelector);
                me.$inputs = me.$starInputs;

                me.registerComponentEvents();
            },

            registerComponentEvents: function() {
                var me = this;

                me._on(me.$starInputs, 'change', function(event) {
                    var $el = $(event.currentTarget);
                    me.$starInputs.parents('.rating-star--outer-container').removeClass('is--active');
                    me.$starInputs.not($el).prop('checked', false);

                    if ($el.is(':checked')) {
                        $el.parents('.rating-star--outer-container').addClass('is--active');
                        $el.removeAttr('disabled');
                    }

                    me.onChange(event);
                });
            },

            updateFacet: function(data) {
                this.updateValueList(data);
            },

            validateElementShouldBeDisabled: function($element, activeIds, ids, checkedIds, value) {
                var val = $element.val();
                if (value) {
                    return false;
                }
                return checkedIds.indexOf(val) === -1;
            },

            setDisabledClass: function($element, disabled) {
                $element.removeClass('is--disabled');
                $element.parents('.rating-star--outer-container').removeClass('is--disabled');
                if (disabled) {
                    $element.addClass('is--disabled');
                    $element.parents('.rating-star--outer-container').addClass('is--disabled');
                }
            }
        }
    };

    /**
     * The actual plugin.
     */
    $.plugin('swFilterComponent', {

        defaults: {
            /**
             * The type of the filter component
             *
             * @String value|range|media|pattern|radio|rating|value-list
             */
            type: 'value',

            /**
             * Defines the unique name, required for ajax reload
             * @String
             */
            facetName: null,

            /**
             * The css class for collapsing the filter component flyout.
             */
            collapseCls: 'is--collapsed',

            /**
             * The css selector for the title element of the filter flyout.
             */
            titleSelector: '.filter-panel--title',

            /**
             * The css selector for checkbox elements in the components.
             */
            checkBoxSelector: 'input[type="checkbox"]'
        },

        /**
         * Initializes the plugin.
         */
        init: function() {
            var me = this;
            me.applyDataAttributes();

            me.type = me.$el.attr('data-filter-type') || me.opts.type;
            me.facetName = me.$el.attr('data-facet-name');

            me.$title = me.$el.find(me.opts.titleSelector);
            me.$siblings = me.$el.siblings('*[data-filter-type]');

            /**
             * Checks if the type of the component uses
             * any special configuration or methods.
             */
            if (specialComponents[me.type] !== undefined) {
                /**
                 * Extends the plugin object with the
                 * corresponding component object.
                 */
                $.extend(me, specialComponents[me.type]);

                /**
                 * Merges the component options into
                 * the plugin options.
                 */
                $.extend(me.opts, me.compOpts);
            }

            me.initComponent();
            me.registerEvents();
            me.subscribeEvents();
        },

        subscribeEvents: function() {
            var me = this;
            $.subscribe(
                me.getEventName('plugin/swListingActions/onGetFilterResultFinished'),
                $.proxy(me.onUpdateFacets, me)
            );
        },

        /**
         * Event listener which triggered after the listing reloaded
         * @param event
         * @param plugin
         * @param response
         */
        onUpdateFacets: function(event, plugin, response) {
            var me = this;

            if (!response.hasOwnProperty('facets')) {
                return;
            }
            var facet = me.getFacet(response.facets, me.facetName);
            me.updateFacet(facet);
        },

        /**
         * Initializes the component based on the type.
         * This method may be overwritten by special components.
         */
        initComponent: function() {
            var me = this;

            me.$inputs = me.$el.find(me.opts.checkBoxSelector);

            me.registerComponentEvents();

            $.publish('plugin/swFilterComponent/onInitComponent', [ me ]);
        },

        /**
         * Registers all necessary global event listeners.
         */
        registerEvents: function() {
            var me = this;

            if (me.type != 'value') {
                me._on(me.$title, 'click', $.proxy(me.toggleCollapse, me, true));
            }

            $.publish('plugin/swFilterComponent/onRegisterEvents', [ me ]);
        },

        /**
         * Registers all necessary events for the component.
         * This method may be overwritten by special components.
         */
        registerComponentEvents: function() {
            var me = this;

            me._on(me.$inputs, 'change', $.proxy(me.onChange, me));

            $.publish('plugin/swFilterComponent/onRegisterComponentEvents', [ me ]);
        },

        /**
         * Called on the change events of each component.
         * Triggers a custom change event on the component,
         * so that other plugins can listen to changes in
         * the different components.
         *
         * @param event
         */
        onChange: function(event) {
            var me = this,
                $el = $(event.currentTarget);

            me.$el.trigger('onChange', [me, $el]);

            $.publish('plugin/swFilterComponent/onChange', [ me, event ]);
        },

        /**
         * Returns the type of the component.
         *
         * @returns {type|*}
         */
        getType: function() {
            return this.type;
        },

        /**
         * Opens the component flyout panel.
         *
         * @param closeSiblings
         */
        open: function(closeSiblings) {
            var me = this;

            if (closeSiblings) {
                me.$siblings.removeClass(me.opts.collapseCls);
            }

            me.$el.addClass(me.opts.collapseCls);

            $.publish('plugin/swFilterComponent/onOpen', [ me ]);
        },

        /**
         * Closes the component flyout panel.
         */
        close: function() {
            var me = this;

            me.$el.removeClass(me.opts.collapseCls);

            $.publish('plugin/swFilterComponent/onClose', [ me ]);
        },

        /**
         * Toggles the viewed state of the component.
         */
        toggleCollapse: function() {
            var me = this,
                shouldOpen = !me.$el.hasClass(me.opts.collapseCls);

            if (me.$el.hasClass('is--disabled')) {
                me.close();
                return;
            }

            if (shouldOpen) {
                me.open(true);
            } else {
                me.close();
            }

            $.publish('plugin/swFilterComponent/onToggleCollapse', [ me, shouldOpen ]);
        },

        /**
         * Destroys the plugin.
         */
        destroy: function() {
            var me = this;

            me._destroy();
        },

        /**
         * Trigger function which called if the filter panel updated and an ajax request reloads the filter data.
         * Provided data array contains the whole response of the ajax request
         * @param data
         */
        updateFacet: function(data) { },

        /**
         * Updates the facet elements with the new provided data
         * This function is used to enable or disable value lists, tree facets, radio lists, single value lists.
         * To switch the behavior for single components, it is possible to overwrite small functions like
         * @param data
         */
        updateValueList: function(data) {
            var me = this, $elements, values, ids, activeIds, checkedIds;

            $elements = me.convertToElementList(me.$inputs);
            values = me.getValues(data, $elements);
            values = me.convertValueIds(values);

            ids = me.getValueIds(values);
            activeIds = me.getActiveValueIds(values);
            checkedIds = me.getElementValues(
                me.getCheckedElements($elements)
            );

            if (me.validateComponentShouldBeDisabled(data, values, checkedIds)) {
                me.disableAll($elements, values);
                return;
            }

            $elements.each(function(index, $element) {
                var val = $element.val() + '';
                var value = me.findValue(val, values);
                var disable = me.validateElementShouldBeDisabled($element, activeIds, ids, checkedIds, value);
                me.disable($element, disable);
                me.setDisabledClass($element.parents('.filter-panel--input'), disable);
            });

            me.disableComponent(me.allDisabled($elements));
        },

        /**
         * Converts the id property of the provided values to an string
         * @param values
         * @returns {array}
         */
        convertValueIds: function(values) {
            values.forEach(function(value, index) {
                value.id = value.id + '';
            });
            return values;
        },

        /**
         * Sets is--disabled class on the filter panel
         * @param disable
         */
        disableComponent: function(disable) {
            if (disable && this.$el.hasClass(this.opts.collapseCls)) {
                this.close();
            }
            this.setDisabledClass(this.$el, disable);
        },

        /**
         * Validate function to check if the filter panel should be disabled
         * @param data
         * @param values
         * @param checkedIds
         * @returns {boolean}
         */
        validateComponentShouldBeDisabled: function(data, values, checkedIds) {
            return data == null && checkedIds.length <= 0;
        },

        /**
         * Disables all provided elements and the filter panel
         * @param $elements
         */
        disableAll: function($elements, values) {
            var me = this;

            $elements.each(function(index, $element) {
                me.disable($element, true);
                me.setDisabledClass($element.parents('.filter-panel--input'), true);
            });
            me.disableComponent(true);
        },

        /**
         * Validate function to check if the provided element should be disabled or enabled.
         * The provided elements contains for example a single value list item or tree item.
         * @param $element
         * @param activeIds
         * @param ids
         * @param checkedIds
         * @param value
         * @returns {boolean}
         */
        validateElementShouldBeDisabled: function($element, activeIds, ids, checkedIds, value) {
            var val = $element.val() + '';

            if (activeIds.indexOf(val) >= 0) {
                return false;
            } else if (ids.indexOf(val) >= 0) {
                return false;
            } else if (checkedIds.indexOf(val) >= 0) {
                return false;
            }
            return true;
        },

        /**
         * Returns the facet data for the provided name
         * @param facets
         * @param name
         * @returns {object|null}
         */
        getFacet: function(facets, name) {
            var found = null;

            facets.forEach(function(facet, index) {
                if (facet.facetName == name) {
                    found = facet;
                    return false;
                }
            });

            return found;
        },

        /**
         * Validates if the provided element is already checked
         * @param $element
         * @returns {boolean}
         */
        isChecked: function($element) {
            return $element.is(':checked');
        },

        /**
         * Returns all elements which have the checked state
         * @param $elements
         * @returns {Array}
         */
        getCheckedElements: function($elements) {
            var actives = [], me = this;

            $elements.each(function(index, $element) {
                if (me.isChecked($element)) {
                    actives.push($element);
                }
            });
            return actives;
        },

        /**
         * Returns an array with all values of the provided elements
         * @param $elements
         * @returns {*}
         */
        getElementValues: function($elements) {
            return $elements.map(function($element) {
                return $element.val() + '';
            });
        },

        /**
         * Finds the value item for the provided id
         * @param val
         * @param values
         * @returns {*}
         */
        findValue: function(val, values) {
            var value = null;
            $(values).each(function(index, item) {
                if (item.id == val) {
                    value = item;
                }
            });
            return value;
        },

        /**
         * Disables or enables the provided element
         * @param $element
         * @param disabled
         */
        disable: function($element, disabled) {
            this.setDisabledClass($element, disabled);
            this.disableElement($element, disabled);
        },

        /**
         * Sets or removes the disabled property for the provided element
         * @param $element
         * @param disabled
         */
        disableElement: function($element, disabled) {
            $element.removeAttr('disabled');
            if (disabled) {
                $element.prop('disabled', 'disabled');
            }
        },

        /**
         * Sets or removes the is--disabled class for the provided element
         * @param $element
         * @param disabled
         */
        setDisabledClass: function($element, disabled) {
            $element.removeClass('is--disabled');
            if (disabled) {
                $element.addClass('is--disabled');
            }
        },

        /**
         * Checks if all provided elements are disabled
         * @param $elements
         * @returns {boolean}
         */
        allDisabled: function($elements) {
            var me = this, allDisabled = true;
            $elements.each(function(index, $element) {
                if (!me.isDisabled($element)) {
                    allDisabled = false;
                }
            });
            return allDisabled;
        },

        /**
         * Validates if the provided element is marked as disabled
         * @param $element
         * @returns {*}
         */
        isDisabled: function($element) {
            return $element.hasClass('is--disabled');
        },

        /**
         * Returns an array of all value ids
         * @param values
         * @returns {Array}
         */
        getValueIds: function(values) {
            var ids = [];
            $(values).each(function(index, value) {
                ids.push(value.id);
            });
            return ids;
        },

        /**
         * Returns all ids of the provided values which marked as active
         * @param values
         * @returns {Array}
         */
        getActiveValueIds: function(values) {
            var ids = [];
            $(values).each(function(index, value) {
                if (value.active) {
                    ids.push(value.id);
                }
            });
            return ids;
        },

        /**
         * Converts the provided html element list to jQuery objects
         * @param elements
         * @returns {*|HTMLElement}
         */
        convertToElementList: function(elements) {
            var $elements = [];
            $(elements).each(function(index, element) {
                $elements.push($(element));
            });
            return $($elements);
        },

        /**
         * Returns a list of values which contained in the provided elements array
         * @param data
         * @param $elements
         * @returns {*}
         */
        getValues: function(data, $elements) {
            var me = this;

            if (!data) {
                return [];
            }

            if (data.hasOwnProperty('values')) {
                return data.values;
            }

            var values = [];

            $(data.facetResults).each(function(index, group) {
                $(group.values).each(function(index, item) {
                    if (me.valueExists(item.id, $elements)) {
                        values.push(item);
                    }
                });
            });
            return values;
        },

        /**
         * Validates if the provided value exists in the provided elements array
         * @param value
         * @param $elements
         * @returns {boolean}
         */
        valueExists: function(value, $elements) {
            var exists = false;

            $elements.each(function(index, input) {
                var val = $(input).val() + '';
                if (val == value) {
                    exists = true;
                    return false;
                }
            });
            return exists;
        }
    });
})(jQuery, window, document, undefined);
