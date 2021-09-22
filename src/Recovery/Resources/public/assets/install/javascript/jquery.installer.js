/**
 * Plugin to check the database configuration and automatically display the available database tables
 */
(function ($, window, document, undefined) {
    "use strict";

    /**
     * Formats a string and replaces the placeholders.
     *
     * @example format('<div class="%0"'>%1</div>, [value for %0], [value for %1], ...)
     *
     * @param {String} str
     * @param {Mixed}
     * @returns {String}
     */
    const format = function (str) {
        for (let i = 1; i < arguments.length; i++) {
            str = str.replace('%' + (i - 1), arguments[i]);
        }
        return str;
    };

    const pluginName = 'ajaxDatabaseSelection';
    const defaults = {url: 'your-url.json'};

    function Plugin(element, options) {
        this.$el = $(element);
        this.opts = $.extend({}, defaults, options);

        this._defaults = defaults;
        this._name = pluginName;

        this.init();
    }

    Plugin.prototype.toggleState = function (checkbox) {

        let select = document.getElementById('c_database_schema');
        let new_input = document.getElementById('c_database_schema_new');

        $(select).attr('disabled', checkbox.checked);
        $(new_input).attr('disabled', !checkbox.checked);
    };

    Plugin.prototype.init = function () {
        const me = this;
        const $el = me.$el;

        const schemaNewCheckbox = document.getElementById('c_database_create_schema_new');
        schemaNewCheckbox.addEventListener('change', e => {
            this.toggleState(e.target);
        });
        this.toggleState(schemaNewCheckbox);

        $el.on('focus', $.proxy(me.onFocus, me));
    };

    Plugin.prototype.onFocus = function () {
        const me = this;
        const $el = me.$el;
        const url = $el.attr('data-url') || me.opts.url;

        $.ajax({
            method: 'post',
            url: url,
            data: $el.parents('form').serialize(),
            dataType: 'json',
            success: $.proxy(me.onSuccess, me)
        });
    };

    Plugin.prototype.onSuccess = function (data) {
        if (data.length === 0) {
            return;
        }

        let indexedData = {};

        for(var key in data) {
            indexedData[data[key].value] = data[key];
        }

        const me = this;
        const oldValue = me.$el.val() || '';
        const fieldName = me.$el.attr('name');
        const opts = me.createSelectOptions(indexedData, oldValue);

        let select = $('<div>', {
            class: 'select-wrapper'
        }).append($('<select>', {
            id: 'c_database_schema',
            name: fieldName,
            class: 'js--database-selection',
            html: opts.join('')
        }));

        me.$el.replaceWith(select);
        select = select.find('select');

        select.change(function (e) {
            const key = e.target.value;
            if(indexedData[key] === undefined || indexedData[key].hasTables) {
                $("#non-empty-db-warning").removeClass("is--hidden");
            } else {
                $("#non-empty-db-warning").addClass("is--hidden");
            }
        });

        for(var key in data) {
            if (!data[key].hasTables) {
                $(select).val(data[key].value);
                break;
            }
        }

        select.trigger('focus');
    };

    Plugin.prototype.createSelectOptions = function (data, oldValue) {
        let opts = [];

        $.each(data, function (i, item) {
            if (oldValue === item.value) {
                opts.push(format('<option selected value="%0">%1</option>', item.value, item.display));
            } else {
                opts.push(format('<option value="%0">%1</option>', item.value, item.display));
            }
        });

        return opts;
    };

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Plugin(this, options));
            }
        });
    };

    $(function () {
        $('*[data-ajaxDatabaseSelection="true"]').ajaxDatabaseSelection();
    })
})(jQuery, window, document);

(function ($, window, undefined) {
    "use strict";

    let backButtonBlocked = false,
        progressConfig = [
            {
                requestUrl: 'importDatabase',
                counterText: shopwareTranslations.counterTextMigrations,
                finalFcnt: function () {
                    $('.btn-primary, .counter-content').removeClass('is--hidden');
                    $('#back').removeClass('disabled');
                    $('.progress').removeClass('progress-info').addClass('progress-success').removeClass('active');
                    $('#start-ajax, .counter-numbers').hide();
                    $(window).unbind('beforeunload');
                    backButtonBlocked = false;

                    $('.database-import-container').hide();
                    $('.database-import-finish').removeClass('is--hidden');
                }
            }
        ], configLen = progressConfig.length;
    let counter = 1;

    const format = function (str) {
        for (var i = 1; i < arguments.length; i++) {
            str = str.replace('%' + (i - 1), arguments[i]);
        }
        return str;
    };

    const startProgress = function (config) {
        const currentConfig = config.shift();
        const progressBar = $('.progress .progress-bar');

        $('.progress').addClass('active');

        progressBar.width("0%");
        counter++;

        currentConfig.maxCount = 0;
        doRequest(0, currentConfig, config);
    };

    const doRequest = function (offset, currentConfig, config) {
        const maxCount = currentConfig.maxCount;
        const progressBar = $('.progress .progress-bar');
        const $totalCountElement = $('.database-import-count-total');
        const $offsetElement = $('.database-import-count-offset');

        $.ajax({
            method: 'POST',
            url: currentConfig.requestUrl,
            data: { offset: offset, total: currentConfig.maxCount }
        }).done(function (data) {
            if (!data.success) {
                $('.alert-error').show().html('<h2>Error</h2>');
                if (data.errorMsg) {
                    $('.alert-error').append("Received the following error message:<br/>" + data.errorMsg);
                }
                $('.alert-error').append("<br><br>Please try to fix this error and restart the update.");
                $('.alert-error').append("<h3>Response</h3><pre>" + JSON.stringify(data) + "</pre>");

                return;
            }

            offset = data.offset;

            $offsetElement.text(offset);

            if (data.total) {
                currentConfig.maxCount = data.total;
                $totalCountElement.text(data.total);
            }

            let progress = offset / maxCount * 100;

            progress = progress + "%";
            progressBar.width(progress);

            if (data.valid) {
                doRequest(offset, currentConfig, config);
            } else {
                if (config.length > 0) {
                    startProgress(config);
                } else {
                    currentConfig.finalFcnt();
                }
            }
        });
    };

    $(document).ajaxError(function (event, jqxhr, settings, exception) {
        $('.alert-error').show().html('<h2>Error</h2> Received an error message.<br><strong>URL:</strong> ' + settings.url + '<br><strong>Message:</strong> ' + exception + "<br><br>Please try to fix this error and restart the update.");
        $('.alert-error').append("<h3>Response</h3>");
        $('.alert-error').append("<pre>" + jqxhr.responseText + "</pre>");
    });

    $(document).ready(function () {
        // Set js class on the html tag
        $('html').removeClass('no-js').addClass('js');

        if($('.database-import').length) {
            startProgress(progressConfig);
            $('#start-ajax').prop('disabled', true);
            $('#back').addClass('disabled');
            $('#back').on('click', function (event) {
                if (backButtonBlocked) {
                    event.preventDefault();
                }
            });
            backButtonBlocked = true;

            $('#skip-import').hide();

            $('.counter-container').removeClass('is--hidden').next('.progress-text').addClass('is--hidden');

            $(window).bind('beforeunload', function () {
                return 'A system update is running.';
            });
        }

        $('.language-selection').bind('change', function () {
            var $this = $(this),
                form = $this.parents('form'),
                action = form.find('.hidden-action').val();

            form.attr('action', action).trigger('submit');
        });

        $('.btn-primary').bind('click', function (event) {
            var $this = $(this),
                form = $this.parents('form');

            form.addClass('is--submitted');
        });

        $('input').bind('keyup', function () {
            const required = $(this).attr('required');
            if (required) {
                var $this = $(this);

                if (!$this.val().length) {
                    $this.removeClass('inline-success').addClass('inline-error');
                } else {
                    $this.removeClass('inline-error').addClass('inline-success');
                }
            }
        });

        const changeLogo = function () {
            var win = $(window),
                winWidth = win.width(),
                logo = $('.header-logo');

            if (winWidth <= 360) {
                logo.attr('src', logo.attr('data-small'));
            } else {
                logo.attr('src', logo.attr('data-normal'));
            }
        };

        $(window).on('resize', changeLogo);
        changeLogo();
    });
})(jQuery, window);

(function ($, undefined) {
    "use strict";

    $('[data-toggle="collapse"]').on('click.toggle', function () {
        var $el = $(this);
        var targetSelector = $el.attr('data-target');

        if (!targetSelector) {
            throw new Error('Please specify a selector with "data-target".');
        }

        var $target = $(targetSelector);

        if ($target.hasClass('is--hidden')) {
            $target.slideDown(300).removeClass('is--hidden');
            $el.addClass('open');
            return;
        }

        $target.slideUp(300).addClass('is--hidden');
        $el.removeClass('open');
    });

    $('input[type=checkbox].toggle, input[type=radio].toggle').on('change.toggle', function () {
        $($(this).attr('data-href')).toggleClass('is--hidden');
    });

    $('input[type=checkbox].removeElem, input[type=radio].removeElem').on('change.removeElem', function () {
        $($(this).attr('data-href-remove')).remove();
    });
})(jQuery);
