;(function ($) {
    var emptyObj = {};

    /**
     * Shopware Last Seen Products Plugin
     *
     * This plugin creates a list of collected articles.
     * Those articles will be collected, when the user opens a detail page.
     * The created list will be showed as a product slider.
     */
    $.plugin('swLastSeenProducts', {

        defaults: {

            /**
             * Limit of the products showed in the slider
             *
             * @property productLimit
             * @type {Number}
             */
            productLimit: 20,

            /**
             * Base url used for uniquely identifying an article
             *
             * @property baseUrl
             * @type {String}
             */
            baseUrl: '/',

            /**
             * ID of the current shop used for uniquely identifying an article.
             *
             * @property shopId
             * @type {Number}
             */
            shopId: 1,

            /**
             * Article that will be added to the list when we are
             * on the detail page.
             *
             * @property currentArticle
             * @type {Object}
             */
            currentArticle: emptyObj,

            /**
             * Selector for the product list used for the product slider
             *
             * @property listSelector
             * @type {String}
             */
            listSelector: '.last-seen-products--slider',

            /**
             * Selector for the product slider container
             *
             * @property containerSelector
             * @type {String}
             */
            containerSelector: '.last-seen-products--container',

            /**
             * Class that will be used for a single product slider items
             *
             * @property itemCls
             * @type {String}
             */
            itemCls: 'last-seen-products--item product-slider--item product--box box--slider',

            /**
             * Class that will be used for the product title
             *
             * @property titleCls
             * @type {String}
             */
            titleCls: 'last-seen-products-item--title product--title',

            /**
             * Class that will be used for the product image
             *
             * @property imageCls
             * @type {String}
             */
            imageCls: 'last-seen-products-item--image product--image',

            /**
             * Picture source when no product image is available
             *
             * @property noPicture
             * @type {String}
             */
            noPicture: ''
        },

        /**
         * Initializes all necessary elements and collects the current
         * article when we are on the detail page.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this;

            me.applyDataAttributes();

            me.$list = me.$el.find(me.opts.listSelector);
            me.$container = me.$list.find(me.opts.containerSelector);

            me.productSlider = me.$list.data('plugin_swProductSlider');

            if (!me.productSlider) {
                return;
            }

            me.storage = StorageManager.getLocalStorage();

            if ($('body').hasClass('is--ctl-detail')) {
                me.collectProduct(me.opts.currentArticle);
                $.subscribe(me.getEventName('plugin/swAjaxVariant/onRequestData'), $.proxy(me.onAjaxVariantChange, me));
            }

            me.createProductList();
        },

        /**
         * Refresh the last seen article if the customer switches between variants
         *
         * @private
         * @method onAjaxVariantChange
         */
        onAjaxVariantChange: function() {
            var me = this;

            me.collectProduct(window.lastSeenProductsConfig.currentArticle);
            me.clearProductList();
            me.createProductList();
        },

        /**
         * Removes all products from the displayed slider
         *
         * @public
         * @method clearProductList
         */
        clearProductList: function () {
            var me = this;

            me.$container.children().remove();
        },

        /**
         * Creates a list of all collected articles and calls
         * the product slider plugin.
         *
         * @public
         * @method createProductList
         */
        createProductList: function () {
            var me = this,
                opts = me.opts,
                itemKey = 'lastSeenProducts-' + opts.shopId + '-' + opts.baseUrl,
                productsJson = me.storage.getItem(itemKey),
                products = productsJson ? JSON.parse(productsJson) : [],
                len = Math.min(opts.productLimit, products.length);

            if (len > 0) {
                me.$el.removeClass('is--hidden');
            }

            $.each(products, function(i, product) {
                if (product.articleId === opts.currentArticle.articleId) {
                    return;
                }

                me.$container.append(me.createTemplate(product));
            });

            me.productSlider.initSlider();

            $.publish('plugin/swLastSeenProducts/onCreateProductList', [ me ]);
        },

        /**
         * Creates a product slider item template.
         *
         * @public
         * @method createTemplate
         * @param {Object} article
         */
        createTemplate: function (article) {
            var me = this,
                $template = $('<div>', {
                    'class': me.opts.itemCls,
                    'html': [
                        me.createProductImage(article),
                        me.createProductTitle(article)
                    ]
                });

            $.publish('plugin/swLastSeenProducts/onCreateTemplate', [ me, $template, article ]);

            return $template;
        },

        /**
         * Creates the product name title by the provided article data
         *
         * @public
         * @method createProductTitle
         * @param {Object} data
         */
        createProductTitle: function (data) {
            var me = this,
                $title = $('<a>', {
                    'rel': 'nofollow',
                    'class': me.opts.titleCls,
                    'title': data.articleName,
                    'href': data.linkDetailsRewritten,
                    'html': data.articleName
                });

            $.publish('plugin/swLastSeenProducts/onCreateProductTitle', [ me, $title, data ]);

            return $title;
        },

        /**
         * Creates a product image with all media queries for the
         * picturefill plugin
         *
         * @public
         * @method createProductImage
         * @param {Object} data
         */
        createProductImage: function (data) {
            var me = this,
                image = data.images[0],
                element,
                imageEl,
                imageMedia,
                srcSet;

            element = $('<a>', {
                'class': me.opts.imageCls,
                'href': data.linkDetailsRewritten,
                'title': data.articleName
            });

            imageEl = $('<span>', { 'class': 'image--element' }).appendTo(element);
            imageMedia = $('<span>', { 'class': 'image--media' }).appendTo(imageEl);

            if (image) {
                srcSet = image.sourceSet;
            } else {
                srcSet = me.opts.noPicture;
            }

            $('<img>', {
                'srcset': srcSet,
                'alt': data.articleName,
                'title': data.articleName
            }).appendTo(imageMedia);

            $.publish('plugin/swLastSeenProducts/onCreateProductImage', [ me, element, data ]);

            return element;
        },

        /**
         * Adds a new article to the local storage for usage in the product slider.
         *
         * @public
         * @method collectProduct
         * @param {Object} newProduct
         */
        collectProduct: function (newProduct) {
            var me = this,
                opts = me.opts,
                itemKey = 'lastSeenProducts-' + opts.shopId + '-' + opts.baseUrl,
                productsJson = me.storage.getItem(itemKey),
                products = productsJson ? $.parseJSON(productsJson) : [],
                linkDetailsQuery = '',
                len = products.length,
                i = 0,
                url,
                urlQuery;

            if (!newProduct || $.isEmptyObject(newProduct)) {
                return;
            }

            for (; i < len; i++) {
                if (products[i] && products[i].articleId === newProduct.articleId) {
                    products.splice(i, 1);
                }
            }

            url = newProduct.linkDetailsRewritten;
            urlQuery = me.extractQueryParameters(url);

            // Remove category from query string
            delete urlQuery.c;
            if ($.param(urlQuery)) {
                linkDetailsQuery = $.param(urlQuery);
                linkDetailsQuery = '?' + linkDetailsQuery;
            }

            // Remove query string from article url
            if (url.indexOf('/sCategory') !== -1) {
                newProduct.linkDetailsRewritten = url.replace(/\/?sCategory\/[0-9]+/i, '');
            } else if (url.indexOf('?') !== -1) {
                newProduct.linkDetailsRewritten = url.substring(0, url.indexOf('?')) + linkDetailsQuery;
            }

            products.splice(0, 0, newProduct);

            while (products.length > opts.productLimit + 1) {
                products.pop();
            }

            me.storage.setItem(itemKey, JSON.stringify(products));

            $.publish('plugin/swLastSeenProducts/onCollectProduct', [ me, newProduct ]);
        },

        /**
         * Extracts the query string as object from a given url
         *
         * @private
         * @method extractQueryParameters
         * @param {string} url
         * @return {Object}
         */
        extractQueryParameters: function (url) {
            var queryParams = {};

            if (url.indexOf('?') === -1) {
                return {};
            }

            // strip everything until query parameters
            url = url.substring(url.indexOf('?'));

            // remove leading "?" symbol
            url = url.substring(1);

            $.each(url.split('&'), function (key, param) {
                param = param.split('=');

                param[0] = decodeURIComponent(param[0]);
                param[1] = decodeURIComponent(param[1]);

                if (param[0].length && param[1].length && !queryParams.hasOwnProperty(param[0])) {
                    queryParams[param[0]] = param[1];
                }
            });

            return queryParams;
        }
    });
}(jQuery));
