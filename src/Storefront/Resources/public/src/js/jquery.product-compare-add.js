;(function($) {
    'use strict';

    /**
     * Shopware Article Compare Add Plugin.
     *
     * The plugin handles the compare add button on every product box.
     */
    $.plugin('swProductCompareAdd', {

        /** Your default options */
        defaults: {
            /** @string compareMenuSelector Listener Class for compare button */
            compareMenuSelector: '.entry--compare',

            /** @string hiddenCls Class which indicates that the element is hidden */
            hiddenCls: 'is--hidden'
        },

        /**
         * Initializes the plugin
         *
         * @returns {Plugin}
         */
        init: function () {
            var me = this;

            // On add article to compare button
            me.$el.on(me.getEventName('click'), '*[data-product-compare-add="true"]', $.proxy(me.onAddArticleCompare, me));

            $.publish('plugin/swProductCompareAdd/onRegisterEvents', [ me ]);
        },

        /**
         * onAddArticleCompare function for adding articles to
         * the compare menu, which will be refreshed by ajax request.
         *
         * @method onAddArticleCompare
         */
        onAddArticleCompare: function (event) {
            var me = this,
                $target = $(event.target),
                $form = $target.closest('form'),
                addArticleUrl;

            event.preventDefault();

            // @deprecated: Don't use anchors for action links. Use forms with method="post" instead.
            if ($target.attr('href')) {
                addArticleUrl = $target.attr('href');
            } else {
                addArticleUrl = $form.attr('action');
            }

            if (!addArticleUrl) {
                return;
            }

            $.overlay.open({
                closeOnClick: false
            });

            $.loadingIndicator.open({
                openOverlay: false
            });

            $.publish('plugin/swProductCompareAdd/onAddArticleCompareBefore', [ me, event ]);

            // Ajax request for adding article to compare list
            $.ajax({
                'url': addArticleUrl,
                'dataType': 'jsonp',
                'success': function(data) {
                    var compareMenu = $(me.opts.compareMenuSelector);

                    if (compareMenu.hasClass(me.opts.hiddenCls)) {
                        compareMenu.removeClass(me.opts.hiddenCls);
                    }

                    // Check if error thrown
                    if (data.indexOf('data-max-reached="true"') !== -1) {
                        $.loadingIndicator.close(function() {
                            $.modal.open(data, {
                                sizing: 'content'
                            });
                        });
                    } else {
                        compareMenu.html(data);

                        // Reload compare menu plugin
                        $('*[data-product-compare-menu="true"]').swProductCompareMenu();

                        // Prevent too fast closing of loadingIndicator and overlay
                        $.loadingIndicator.close(function() {
                            $('html, body').animate({
                                scrollTop: ($('.top-bar').offset().top)
                            }, 'slow');

                            $.overlay.close();
                        });
                    }

                    $.publish('plugin/swProductCompareAdd/onAddArticleCompareSuccess', [ me, event, data, compareMenu ]);
                }
            });

            $.publish('plugin/swProductCompareAdd/onAddArticleCompare', [ me, event ]);
        },

        /** Destroys the plugin */
        destroy: function () {
            this.$el.off(this.getEventName('click'));

            this._destroy();
        }
    });
})(jQuery);
