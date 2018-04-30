;(function($, window, document) {
    'use strict';

    var $document = $(document);

    /**
     * Rounds the given value to the chosen base.
     *
     * Example: 5.46 with a base of 0.5 will round to 5.5
     *
     * @param value
     * @param base
     * @param method | round / floor / ceil
     * @returns {number}
     */
    function round(value, base, method) {
        var rounding = method || 'round',
            b = base || 1,
            factor = 1 / b;

        return Math[rounding](value * factor) / factor;
    }

    /**
     * Rounds an integer to the next 5er brake
     * based on the sum of digits.
     *
     * @param value
     * @param method
     * @returns {number}
     */
    function roundPretty(value, method) {
        var rounding = method || 'round',
            digits = countDigits(value),
            step = (digits > 1) ? 2 : 1,
            base = 5 * Math.pow(10, digits - step);

        return round(value, base, rounding);
    }

    /**
     * Get the sum of digits before the comma of a number.
     *
     * @param value
     * @returns {number}
     */
    function countDigits(value) {
        return ~~(Math.log(Math.floor(value)) / Math.LN10 + 1);
    }

    /**
     * Clamps a number between a min and a max value.
     *
     * @param value
     * @param min
     * @param max
     * @returns {number}
     */
    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    /**
     * Converts a value to an integer.
     *
     * @param value
     * @returns {Number}
     */
    function int(value) {
        return parseFloat(value);
    }

    $.plugin('swRangeSlider', {

        defaults: {
            /**
             * The css class for the range slider container element.
             */
            sliderContainerCls: 'range-slider--container',

            /**
             * The css class for the range bar element.
             */
            rangeBarCls: 'range-slider--range-bar',

            /**
             * The css class for the handle elements at the start and end of the range bar.
             */
            handleCls: 'range-slider--handle',

            /**
             * The css class for the handle element at the min position.
             */
            handleMinCls: 'is--min',

            /**
             * The css class for the handle element at the max position.
             */
            handleMaxCls: 'is--max',

            /**
             * The css class for active handle elements which get dragged.
             */
            activeDraggingCls: 'is--dragging',

            /**
             * The selector for the hidden input field which holds the min value.
             */
            minInputElSelector: '*[data-range-input="min"]',

            /**
             * The selector for the hidden input field which holds the max value.
             */
            maxInputElSelector: '*[data-range-input="max"]',

            /**
             * The selector for the label which displays the min value.
             */
            minLabelElSelector: '*[data-range-label="min"]',

            /**
             * The selector for the label which displays the max value.
             */
            maxLabelElSelector: '*[data-range-label="max"]',

            /**
             * An example string for the format of the value label.
             */
            labelFormat: '',

            /**
             * Turn pretty rounding for cleaner steps on and off.
             */
            roundPretty: false,

            /**
             * The min value which the slider should show on start.
             */
            startMin: 20,

            /**
             * The max value which the slider should show on start.
             */
            startMax: 80,

            /**
             * The minimal value you can slide to.
             */
            rangeMin: 0,

            /**
             * The maximum value you can slide to.
             */
            rangeMax: 100,

            /**
             * The number of steps the slider is divided in.
             */
            stepCount: 100,

            /**
             * Function for calculation
             */
            stepCurve: 'linear'
        },

        init: function() {
            var me = this;

            me.applyDataAttributes();

            me.$minInputEl = me.$el.find(me.opts.minInputElSelector);
            me.$maxInputEl = me.$el.find(me.opts.maxInputElSelector);

            me.$minLabel = me.$el.find(me.opts.minLabelElSelector);
            me.$maxLabel = me.$el.find(me.opts.maxLabelElSelector);

            me.dragState = false;
            me.dragType = 'min';

            me.createSliderTemplate();
            me.validateStepCurve();

            me.computeBaseValues();
            me.registerEvents();
        },

        validateStepCurve: function() {
            var me = this,
                validCurves = ['linear', 'log'];

            me.opts.stepCurve = me.opts.stepCurve.toString().toLowerCase();

            if (validCurves.indexOf(me.opts.stepCurve) < 0) {
                me.opts.stepCurve = 'linear';
            }
        },

        registerEvents: function() {
            var me = this;

            me._on(me.$minHandle, 'mousedown touchstart', $.proxy(me.onStartDrag, me, 'min', me.$minHandle));
            me._on(me.$maxHandle, 'mousedown touchstart', $.proxy(me.onStartDrag, me, 'max', me.$maxHandle));

            me._on($document, 'mouseup touchend', $.proxy(me.onEndDrag, me));
            me._on($document, 'mousemove touchmove', $.proxy(me.slide, me));

            $.publish('plugin/swRangeSlider/onRegisterEvents', [ me ]);
        },

        createSliderTemplate: function() {
            var me = this;

            me.$rangeBar = me.createRangeBar();
            me.$container = me.createRangeContainer();

            me.$minHandle = me.createHandle('min');
            me.$maxHandle = me.createHandle('max');

            me.$minHandle.appendTo(me.$rangeBar);
            me.$maxHandle.appendTo(me.$rangeBar);
            me.$rangeBar.appendTo(me.$container);
            me.$container.prependTo(me.$el);
        },

        createRangeContainer: function() {
            var me = this,
                $container = $('<div>', {
                    'class': me.opts.sliderContainerCls
                });

            $.publish('plugin/swRangeSlider/onCreateRangeContainer', [ me, $container ]);

            return $container;
        },

        createRangeBar: function() {
            var me = this,
                $bar = $('<div>', {
                    'class': me.opts.rangeBarCls
                });

            $.publish('plugin/swRangeSlider/onCreateRangeBar', [ me, $bar ]);

            return $bar;
        },

        createHandle: function(type) {
            var me = this,
                typeClass = (type == 'max') ? me.opts.handleMaxCls : me.opts.handleMinCls,
                $handle = $('<div>', {
                    'class': me.opts.handleCls + ' ' + typeClass
                });

            $.publish('plugin/swRangeSlider/onCreateHandle', [ me, $handle ]);

            return $handle;
        },

        computeBaseValues: function() {
            var me = this;

            me.minRange = int(me.opts.rangeMin);
            me.maxRange = int(me.opts.rangeMax);

            if (me.opts.roundPretty) {
                me.minRange = roundPretty(me.minRange, 'floor');
                me.maxRange = roundPretty(me.maxRange, 'ceil');
            }

            me.range = me.maxRange - me.minRange;
            me.stepSize = me.range / int(me.opts.stepCount);
            me.stepWidth = 100 / int(me.opts.stepCount);

            me.minValue = (me.opts.startMin === me.opts.rangeMin || me.opts.startMin <= me.minRange) ? me.minRange : int(me.opts.startMin);
            me.maxValue = (me.opts.startMax === me.opts.rangeMax || me.opts.startMax >= me.maxRange) ? me.maxRange : int(me.opts.startMax);

            if (me.maxValue == me.minValue || me.maxValue == 0) {
                me.maxValue = me.maxRange;
            }

            $.publish('plugin/swRangeSlider/onComputeBaseValues', [ me, me.minValue, me.maxValue ]);

            me.setRangeBarPosition(me.minValue, me.maxValue);
            me.updateLayout();
        },

        setRangeBarPosition: function(minValue, maxValue) {
            var me = this,
                min = minValue || me.minValue,
                max = maxValue || me.maxValue,
                left = me.getPositionByValue(min),
                right = me.getPositionByValue(max),
                width = right - left;

            me.$rangeBar.css({
                'left': left + '%',
                'width': width + '%'
            });

            $.publish('plugin/swRangeSlider/onSetRangeBarPosition', [ me, me.$rangeBar, minValue, maxValue ]);
        },

        setMin: function(min, updateInput) {
            var me = this,
                update = updateInput || false;

            min = (min === me.opts.rangeMin || min <= me.minRange) ? me.minRange : int(min);
            me.minValue = min;

            if (update) {
                me.updateMinInput(min);
            }

            me.setRangeBarPosition();
            me.updateLayout();

            $.publish('plugin/swRangeSlider/onSetMin', [ me, min, updateInput ]);
        },

        setMax: function(max, updateInput) {
            var me = this,
                update = updateInput || false;

            max = (max === me.opts.rangeMax || max >= me.maxRange) ? me.maxRange : int(max);
            me.maxValue = max;

            if (update) {
                me.updateMaxInput(max);
            }

            me.setRangeBarPosition();
            me.updateLayout();

            $.publish('plugin/swRangeSlider/onSetMax', [ me, max, updateInput ]);
        },

        reset: function(param) {
            var me = this;

            if (param == 'max') {
                me.maxValue = me.maxRange;
                me.$maxInputEl.attr('disabled', 'disabled')
                    .val(me.maxRange)
                    .trigger('change');
            } else {
                me.minValue = me.minRange;
                me.$minInputEl.attr('disabled', 'disabled')
                    .val(me.minRange)
                    .trigger('change');
            }

            me.setRangeBarPosition();
            me.updateLayout();

            $.publish('plugin/swRangeSlider/onReset', [ me, param ]);
        },

        onStartDrag: function(type, $handle) {
            var me = this;

            $handle.addClass(me.opts.activeDraggingCls);

            me.dragState = true;
            me.dragType = type;

            $.publish('plugin/swRangeSlider/onStartDrag', [ me, type, $handle ]);
        },

        onEndDrag: function() {
            var me = this;

            if (!me.dragState) {
                return;
            }
            me.dragState = false;

            me.updateLayout();

            me.$minHandle.removeClass(me.opts.activeDraggingCls);
            me.$maxHandle.removeClass(me.opts.activeDraggingCls);

            if (me.dragType == 'max') {
                me.updateMaxInput(me.maxValue);
            } else {
                me.updateMinInput(me.minValue);
            }

            $(me).trigger('rangeChange', me);

            $.publish('plugin/swRangeSlider/onEndDrag', [ me, me.dragType ]);
        },

        slide: function(event) {
            var me = this;

            if (!me.dragState) {
                return;
            }

            var pageX = (event.originalEvent.touches) ? event.originalEvent.touches[0].pageX : event.pageX,
                offset = me.$container.offset(),
                width = me.$container.innerWidth(),
                mouseX = pageX - offset.left,
                xPercent = clamp(round((100 / width * mouseX), me.stepWidth, 'round'), 0, 100),
                value = me.getValueByPosition(xPercent);

            event.preventDefault();

            if (me.dragType == 'max') {
                var minValue = me.getValueByPosition(me.getPositionByValue(me.minValue) + me.stepWidth);
                me.setMax(clamp(value, minValue, me.maxRange));
            } else {
                var maxValue = me.getValueByPosition(me.getPositionByValue(me.maxValue) - me.stepWidth);
                me.setMin(clamp(value, me.minRange, maxValue));
            }

            $.publish('plugin/swRangeSlider/onSlide', [ me, event, xPercent, value ]);
        },

        updateMinInput: function(value) {
            var me = this;

            if (!me.$minInputEl.length) {
                return;
            }

            if (value <= me.opts.rangeMin) {
                me.$minInputEl.prop('disabled', 'disabled')
                    .trigger('change');
            } else {
                me.$minInputEl.val(value.toFixed(2))
                    .removeAttr('disabled')
                    .trigger('change');
            }

            $.publish('plugin/swRangeSlider/onUpdateMinInput', [ me, me.$minInputEl, value ]);
        },

        updateMaxInput: function(value) {
            var me = this;

            if (!me.$maxInputEl.length) {
                return;
            }

            if (value >= me.opts.rangeMax) {
                me.$maxInputEl.prop('disabled', 'disabled')
                    .trigger('change');
            } else {
                me.$maxInputEl.val(value.toFixed(2))
                    .removeAttr('disabled')
                    .trigger('change');
            }

            $.publish('plugin/swRangeSlider/onUpdateMaxInput', [ me, me.$maxInputEl, value ]);
        },

        updateMinLabel: function(value) {
            var me = this;

            if (me.$minLabel.length) {
                me.$minLabel.html(me.formatValue(value));

                $.publish('plugin/swRangeSlider/onUpdateMinLabel', [ me, me.$minLabel, value ]);
            }
        },

        updateMaxLabel: function(value) {
            var me = this;

            if (me.$maxLabel.length) {
                me.$maxLabel.html(me.formatValue(value));

                $.publish('plugin/swRangeSlider/onUpdateMaxLabel', [ me, me.$maxLabel, value ]);
            }
        },

        updateLayout: function(minValue, maxValue) {
            var me = this,
                min = minValue || me.minValue,
                max = maxValue || me.maxValue;

            me.updateMinLabel(min);
            me.updateMaxLabel(max);

            $.publish('plugin/swRangeSlider/onUpdateLayout', [ me, minValue, maxValue ]);
        },

        roundValue: function(value) {
            var me = this;

            if (value < 10) {
                value = me.roundTo(value, 0.10);
            } else if (value < 100) {
                value = me.roundTo(value, 1);
            } else {
                value = me.roundTo(value, 5);
            }

            return value;
        },

        formatValue: function(value) {
            var me = this;

            $.publish('plugin/swRangeSlider/onFormatValueBefore', [ me, value ]);

            if (value != me.minRange && value != me.maxRange) {
                value = me.roundValue(value);
            }

            if (!me.opts.labelFormat.length) {
                return value.toFixed(2);
            }

            value = Math.round(value * 100) / 100;
            value = value.toFixed(2);

            if (me.opts.labelFormat.indexOf('0.00') >= 0) {
                value = me.opts.labelFormat.replace('0.00', value);
            } else {
                value = value.replace('.', ',');
                value = me.opts.labelFormat.replace('0,00', value);
            }

            $.publish('plugin/swRangeSlider/onFormatValue', [ me, value ]);

            return value;
        },

        roundTo: function(value, num) {
            var resto = value % num;

            if (resto <= (num / 2)) {
                return value - resto;
            } else {
                return value + num - resto;
            }
        },

        getPositionByValue: function(value) {
            var me = this;

            if (me.opts.stepCurve == 'log') {
                return me._getPositionLog(value);
            }

            return me._getPositionLinear(value);
        },

        _getPositionLog: function(value) {
            var me = this,
                minp = 0,
                maxp = me.opts.stepCount,
                minv = Math.log(me.opts.rangeMin),
                maxv = Math.log(me.opts.rangeMax),
                scale = (maxv - minv) / (maxp - minp),
                pos = minp + (Math.log(value) - minv) / scale;

            pos = Math.round(pos * me.stepWidth);

            return pos > 0 && pos || 0;
        },

        _getPositionLinear: function(value) {
            var me = this;

            return 100 / me.range * (value - me.minRange);
        },

        getValueByPosition: function(position) {
            var me = this;

            if (me.opts.stepCurve == 'log') {
                return me._getValueLog(position);
            }

            return me._getValueLinear(position);
        },

        _getValueLinear: function(position) {
            var me = this;

            return (me.range / 100 * position) + me.minRange;
        },

        _getValueLog: function(position) {
            var me = this;

            if (position === 0) {
                return me.minRange;
            } else if (position === 100) {
                return me.maxRange;
            }

            var minp = 0,
                maxp = me.opts.stepCount,
                minv = Math.log(me.opts.rangeMin),
                maxv = Math.log(me.opts.rangeMax),
                scale = (maxv - minv) / (maxp - minp);

            position = position / me.stepWidth;

            return Math.exp(minv + scale * (position - minp));
        },

        getStepWidth: function(value) {
            var me = this;

            if (me.opts.stepCurve == 'log') {
                return value;
            }

            return me.stepWidth;
        },

        destroy: function() {
            var me = this;

            me._destroy();
        }
    });
})(jQuery, window, document);
