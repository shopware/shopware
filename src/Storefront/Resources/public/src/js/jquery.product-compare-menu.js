;(function($) {
    'use strict';

    /**
     * Shopware product Compare Plugin.
     *
     * The plugin controlls the topbar-navigation dropdown menu für product comparisons.
     */
    $.plugin('swProductCompareMenu', {

        /** Your default options */
        defaults: {
            /** @string compareMenuSelector HTML class for the topbarnavigation menu wrapper */
            compareMenuSelector: '.entry--compare',

            /** @string startCompareSelector HTML class for the start compare button */
            startCompareSelector: '.btn--compare-start',

            /** @string deleteCompareSelector HTML class for the cancel compare button */
            deleteCompareSelector: '.btn--compare-delete',

            /** @string deleteCompareItemSelector HTML class for delete single product from comparison */
            deleteCompareItemSelector: '.btn--item-delete',

            /** @string modalSelector HTML class for modal window */
            modalSelector: '.js--modal',

            /** @string modalContentInnerSelector HTML class for modal inner content */
            modalContentInnerSelector: '.modal--compare',

            /** @string compareEntriesSelector Selector for switching between single remove or full plugin reload */
            compareEntriesSelector: '.compare--list .compare--entry',

            /** @string compareEntry Selector for single compare item inside the dropdown */
            compareEntrySelector: '.compare--entry',

            /** @string hiddenCls Class which indicates that the element is hidden */
            hiddenCls: 'is--hidden'
        },

        /**
         * Initializes the plugin
         *
         * @returns {Plugin}
         */
        init: function () {
            var me = this,
                $compareMenu = $(me.opts.compareMenuSelector);

            if (!$compareMenu.is(':empty')) {
                $compareMenu.removeClass(me.opts.hiddenCls);
            }

            // on start compare
            me._on(me.opts.startCompareSelector, 'touchstart click', $.proxy(me.onStartCompare, me));

            // On cancel compare
            me._on(me.opts.deleteCompareSelector, 'touchstart click', $.proxy(me.onDeleteCompare, me));

            // On delete single product item from comparison
            me._on(me.opts.deleteCompareItemSelector, 'touchstart click', $.proxy(me.onDeleteItem, me));

            $.publish('plugin/swProductCompareMenu/onRegisterEvents', [ me ]);
        },

        /**
         * Opens the comparison modal by startCompareSelector.
         *
         * @public
         * @method onStartCompare
         */
        onStartCompare: function (event) {
            event.preventDefault();

            var me = this,
                startCompareBtn = me.$el.find(me.opts.startCompareSelector),
                modalUrl = startCompareBtn.attr('href'),
                modalTitle = startCompareBtn.attr('data-modal-title');

            $.loadingIndicator.open({
                closeOnClick: false
            });

            $.publish('plugin/swProductCompareMenu/onStartCompareBefore', [ me ]);

            // Load compare modal before opening modal box
            $.ajax({
                'url': modalUrl,
                'dataType': 'jsonp',
                'success': function(template) {
                    $.publish('plugin/swProductCompareMenu/onStartCompareSuccess', [ me, template ]);

                    $.loadingIndicator.close(function() {
                        $.modal.open(template, {
                            title: modalTitle,
                            sizing: 'content'
                        });

                        // Auto sizing for width
                        var templateWidth = $(me.opts.modalSelector).find(me.opts.modalContentInnerSelector).outerWidth();
                        $(me.opts.modalSelector).css('width', templateWidth);

                        picturefill();

                        // Resize every property row height to biggest height in cell
                        var maxRows = 0;
                        $('.entry--property').each(function () {
                            var row = ~~($(this).attr('data-property-row'));
                            if (row > maxRows) {
                                maxRows = row;
                            }
                        });

                        var maximumHeight,
                            rowSelector,
                            i = 1;

                        for (; i <= maxRows; i++) {
                            rowSelector = '.entry--property[data-property-row="' + i + '"]';

                            maximumHeight = 0;
                            $(rowSelector).each(function () {
                                var rowHeight = $(this).height();

                                if (rowHeight > maximumHeight) {
                                    maximumHeight = rowHeight;
                                }
                            });

                            $(rowSelector).height(maximumHeight);
                        }
                        $.publish('plugin/swProductCompareMenu/onStartCompareFinished', [ me, template ]);
                    });
                }
            });

            $.publish('plugin/swProductCompareMenu/onStartCompare', [ me ]);
        },

        /**
         * Cancel the compare
         *
         * @method onDeleteCompare
         */
        onDeleteCompare: function (event) {
            var me = this,
                $target = $(event.currentTarget),
                deleteCompareBtn = me.$el.find(me.opts.deleteCompareSelector),
                $form = deleteCompareBtn.closest('form'),
                $menu = $(me.opts.compareMenuSelector),
                deleteUrl;

            event.preventDefault();

            // @deprecated: Don't use anchors for action links. Use forms with method="post" instead.
            if ($target.attr('href')) {
                deleteUrl = $target.attr('href');
            } else {
                deleteUrl = $form.attr('action');
            }

            $.ajax({
                'url': deleteUrl,
                'dataType': 'jsonp',
                'success': function () {
                    $menu.empty().addClass(me.opts.hiddenCls);

                    $.publish('plugin/swProductCompareMenu/onDeleteCompareSuccess', [ me ]);
                }
            });

            $.publish('plugin/swProductCompareMenu/onDeleteCompare', [ me ]);
        },

        /**
         * Delete one product item from comparison
         *
         * @method onDeleteItem
         */
        onDeleteItem: function (event) {
            event.preventDefault();

            var me = this,
                $deleteBtn = $(event.currentTarget),
                $form = $deleteBtn.closest('form'),
                rowElement = $deleteBtn.closest(me.opts.compareEntrySelector),
                compareCount = $(me.opts.compareEntriesSelector).length,
                deleteUrl;

            // @deprecated: Don't use anchors for action links. Use forms with method="post" instead.
            if ($deleteBtn.attr('href')) {
                deleteUrl = $deleteBtn.attr('href');
            } else {
                deleteUrl = $form.attr('action');
            }

            if (compareCount > 1) {
                // slide up and remove product from unordered list
                rowElement.slideUp('fast', function() {
                    rowElement.remove();
                });

                // update compare counter
                $('.compare--quantity').html('(' + (compareCount - 1) + ')');

                // remove product silent in the background
                $.ajax({
                    'url': deleteUrl,
                    'dataType': 'jsonp',
                    'success': function (response) {
                        $.publish('plugin/swProductCompareMenu/onDeleteItemSuccess', [ me, response ]);
                    }
                });
            } else {
                // remove last product, reload full compare plugin
                $.ajax({
                    'url': deleteUrl,
                    'dataType': 'jsonp',
                    'success': function (response) {
                        $(me.opts.compareMenuSelector).empty().addClass(me.opts.hiddenCls);

                        // Reload compare menu plugin
                        $('*[data-product-compare-menu="true"]').swProductCompareMenu();

                        $.publish('plugin/swProductCompareMenu/onDeleteItemSuccess', [ me, response ]);
                    }
                });
            }

            $.publish('plugin/swProductCompareMenu/onDeleteItem', [ me, event, deleteUrl ]);
        },

        /** Destroys the plugin */
        destroy: function () {
            this._destroy();
        }
    });
})(jQuery);
