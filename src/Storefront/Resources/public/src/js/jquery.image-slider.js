;(function ($, Modernizr, window, Math) {
    'use strict';

    var transitionProperty = StateManager.getVendorProperty('transition'),
        transformProperty = StateManager.getVendorProperty('transform'),
        killEvent = function (event) {
            event.preventDefault();
            event.stopPropagation();
        };

    /**
     * Image Slider Plugin.
     *
     * This plugin provides the functionality for an advanced responsive image slider.
     * It has support for thumbnails, arrow controls, touch controls and automatic sliding.
     *
     * Example DOM Structure:
     *
     * <div class="image-slider" data-image-slider="true">
     *      <div class="image-slider--container">
     *          <div class="image-slider--slide">
     *              <div class="image-slider--item"></div>
     *              <div class="image-slider--item"></div>
     *              <div class="image-slider--item"></div>
     *          </div>
     *      </div>
     *      <div class="image-slider--thumbnails">
     *          <div class="image-slider--thumbnails-slide">
     *              <a class="thumbnail--link"></a>
     *              <a class="thumbnail--link"></a>
     *              <a class="thumbnail--link"></a>
     *          </div>
     *      </div>
     * </div>
     */
    $.plugin('swImageSlider', {

        defaults: {

            /**
             * Set the speed of the slide animation in ms.
             *
             * @property animationSpeed
             * @type {Number}
             */
            animationSpeed: 350,

            /**
             * Easing function for the slide animations.
             * Will only be set when transitions and
             * transforms are supported by the browser.
             *
             * @property animationEasing
             * @type {String}
             */
            animationEasing: 'cubic-bezier(.2,.89,.75,.99)',

            /**
             * Turn thumbnail support on and off.
             *
             * @property thumbnails
             * @type {Boolean}
             */
            thumbnails: true,

            /**
             * Turn support for a small dot navigation on and off.
             *
             * @property dotNavigation
             * @type {Boolean}
             */
            dotNavigation: true,

            /**
             * Turn arrow controls on and off.
             *
             * @property arrowControls
             * @type {Boolean}
             */
            arrowControls: true,

            /**
             * Turn touch controls on and off.
             *
             * @property touchControls
             * @type {Boolean}
             */
            touchControls: true,

            /**
             * Whether or not the automatic slide feature should be active.
             *
             * @property autoSlide
             * @type {Boolean}
             */
            autoSlide: false,

            /**
             * Whether or not the pinch to zoom feature should be active.
             *
             * @property pinchToZoom
             * @type {Boolean}
             */
            pinchToZoom: false,

            /**
             * Whether or not the swipe to slide feature should be active.
             *
             * @property swipeToSlide
             * @type {Boolean}
             */
            swipeToSlide: true,

            /**
             * Whether or not the pull preview feature should be active.
             *
             * @property pullPreview
             * @type {Boolean}
             */
            pullPreview: false,

            /**
             * Whether or not the double tap/click should be used to zoom in/out..
             *
             * @property doubleTap
             * @type {Boolean}
             */
            doubleTap: false,

            /**
             * Time in milliseconds in which two touches should be
             * registered as a double tap.
             *
             * @property doubleTapPeriod
             * @type {Number}
             */
            doubleTapPeriod: 400,

            /**
             * Whether or not the scrolling should be prevented when moving on the slide.
             *
             * @property preventScrolling
             * @type {Boolean}
             */
            preventScrolling: false,

            /**
             * The minimal zoom factor an image can have.
             *
             * @property minZoom
             * @type {Number}
             */
            minZoom: 1,

            /**
             * The maximal zoom factor an image can have.
             * Can either be a number or 'auto'.
             *
             * If set to 'auto', you can only zoom to the original image size.
             *
             * @property maxZoom
             * @type {Number|String}
             */
            maxZoom: 'auto',

            /**
             * The distance in which a pointer move is registered.
             *
             * @property moveTolerance
             * @type {Number}
             */
            moveTolerance: 30,

            /**
             * The distance you have to travel to recognize a swipe in pixels.
             *
             * @property swipeTolerance
             * @type {Number}
             */
            swipeTolerance: 50,

            /**
             * Time period in which the swipe gesture will be registered.
             *
             * @property swipePeriod
             * @type {Number}
             */
            swipePeriod: 250,

            /**
             * Tolerance of the pull preview.
             * When this tolerance is exceeded,
             * the image will slide to the next/previous image.
             * Can either be a number that represent a pixel value or
             * 'auto' to take a third of the viewport as the tolerance.
             *
             * @property pullTolerance
             * @type {String|Number}
             */
            pullTolerance: 'auto',

            /**
             * The image index that will be set when the plugin gets initialized.
             *
             * @property startIndex
             * @type {Number}
             */
            startIndex: 0,

            /**
             * Set the speed for the automatic sliding in ms.
             *
             * @property autoSlideInterval
             * @type {Number}
             */
            autoSlideInterval: 5000,

            /**
             * This property indicates whether or not the slides are looped.
             * If this flag is active and the last slide is active, you can
             * slide to the next one and it will start from the beginning.
             *
             * @property loopSlides
             * @type {Boolean}
             */
            loopSlides: false,

            /**
             * The selector for the container element holding the actual image slider.
             *
             * @property imageContainerSelector
             * @type {String}
             */
            imageContainerSelector: '.image-slider--container',

            /**
             * The selector for the slide element which slides inside the image container.
             *
             * @property imageSlideSelector
             * @type {String}
             */
            imageSlideSelector: '.image-slider--slide',

            /**
             * The selector fot the container element holding the thumbnails.
             *
             * @property thumbnailContainerSelector
             * @type {String}
             */
            thumbnailContainerSelector: '.image-slider--thumbnails',

            /**
             * The selector for the element that slides inside the thumbnail container.
             * This element should be contained in the thumbnail container.
             *
             * @property thumbnailSlideSelector
             * @type {String}
             */
            thumbnailSlideSelector: '.image-slider--thumbnails-slide',

            /**
             * Selector of a single thumbnail.
             * Those thumbnails should be contained in the thumbnail slide.
             *
             * @property thumbnailSlideSelector
             * @type {String}
             */
            thumbnailSelector: '.thumbnail--link',

            /**
             * The selector for the dot navigation container.
             *
             * @property dotNavSelector
             * @type {String}
             */
            dotNavSelector: '.image-slider--dots',

            /**
             * The selector for each dot link in the dot navigation.
             *
             * @property dotLinkSelector
             * @type {String}
             */
            dotLinkSelector: '.dot--link',

            /**
             * Class that will be applied to both the previous and next arrow.
             *
             * @property thumbnailArrowCls
             * @type {String}
             */
            thumbnailArrowCls: 'thumbnails--arrow',

            /**
             * The css class for the left slider arrow.
             *
             * @property leftArrowCls
             * @type {String}
             */
            leftArrowCls: 'arrow is--left',

            /**
             * The css class for the right slider arrow.
             *
             * @property rightArrowCls
             * @type {String}
             */
            rightArrowCls: 'arrow is--right',

            /**
             * The css class for a top positioned thumbnail arrow.
             *
             * @property thumbnailArrowTopCls
             * @type {String}
             */
            thumbnailArrowTopCls: 'is--top',

            /**
             * The css class for a left positioned thumbnail arrow.
             *
             * @property thumbnailArrowLeftCls
             * @type {String}
             */
            thumbnailArrowLeftCls: 'is--left',

            /**
             * The css class for a right positioned thumbnail arrow.
             *
             * @property thumbnailArrowRightCls
             * @type {String}
             */
            thumbnailArrowRightCls: 'is--right',

            /**
             * The css class for a bottom positioned thumbnail arrow.
             *
             * @property thumbnailArrowBottomCls
             * @type {String}
             */
            thumbnailArrowBottomCls: 'is--bottom',

            /**
             * The css class for active states of the arrows.
             *
             * @property activeStateClass
             * @type {String}
             */
            activeStateClass: 'is--active',

            /**
             * Class that will be appended to the image container
             * when the user is grabbing an image
             *
             * @property grabClass
             * @type {String}
             */
            dragClass: 'is--dragging',

            /**
             * Class that will be appended to the thumbnail container
             * when no other thumbnails are available
             *
             * @property noThumbClass
             * @type {String}
             */
            noThumbClass: 'no--thumbnails',

            /**
             * Selector for the image elements in the slider.
             * Those images should be contained in the image slide element.
             *
             * @property imageSelector
             * @type {String}
             */
            imageSelector: '.image-slider--item img',

            /**
             * Selector for a single slide item.
             * Those elements should be contained in the image slide element.
             *
             * @property itemSelector
             * @type {String}
             */
            itemSelector: '.image-slider--item',

            /**
             * Class that will be appended when an element should not be shown.
             *
             * @property hiddenClass
             * @type {String}
             */
            hiddenClass: 'is--hidden'
        },

        /**
         * Method for the plugin initialisation.
         * Merges the passed options with the data attribute configurations.
         * Creates and references all needed elements and properties.
         * Calls the registerEvents method afterwards.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts;

            // Merge the data attribute configurations with the default ones
            me.applyDataAttributes();

            /**
             * Container of the slide element.
             * Acts as a wrapper and container for additional
             * elements like arrows.
             *
             * @private
             * @property _$slideContainer
             * @type {jQuery}
             */
            me._$slideContainer = me.$el.find(opts.imageContainerSelector);

            /**
             * Container of the slide element.
             * Acts as a wrapper and container for additional
             * elements like arrows.
             *
             * @private
             * @property $slide
             * @type {jQuery}
             */
            me._$slide = me._$slideContainer.find(opts.imageSlideSelector);

            /**
             * Current index of the active slide.
             * Will be used for correctly showing the active thumbnails / dot.
             *
             * @private
             * @property _slideIndex
             * @type {Number}
             */
            me._slideIndex = opts.startIndex;

            /**
             * ID of the setTimeout that will be called if the
             * auto slide option is active.
             * Wil be used for removing / resetting the timer.
             *
             * @private
             * @property _slideInterval
             * @type {Number}
             */
            me._slideInterval = 0;

            /**
             * References the currently active image.
             * This element is contained in a jQuery wrapper.
             *
             * @private
             * @property _$currentImage
             * @type {jQuery}
             */
            me._$currentImage = null;

            /**
             * Minimal zoom factor for image scaling
             *
             * @private
             * @property _minZoom
             * @type {Number}
             */
            me._minZoom = parseFloat(opts.minZoom) || 1;

            /**
             * Maximum zoom factor for image scaling
             *
             * @private
             * @property _maxZoom
             * @type {Number}
             */
            me._maxZoom = parseFloat(opts.maxZoom);

            /**
             * Whether or not the scale should be recalculated for each image.
             *
             * @private
             * @property _autoScale
             * @type {Boolean}
             */
            me._autoScale = !me._maxZoom && (me._maxZoom = me._minZoom);

            if (opts.thumbnails) {
                me._$thumbnailContainer = me.$el.find(opts.thumbnailContainerSelector);
                me._$thumbnailSlide = me._$thumbnailContainer.find(opts.thumbnailSlideSelector);
                me._thumbnailOrientation = me.getThumbnailOrientation();
                me._thumbnailOffset = 0;
                me.createThumbnailArrows();
            }

            if (opts.dotNavigation) {
                me._$dotNav = me.$el.find(opts.dotNavSelector);
                me._$dots = me._$dotNav.find(opts.dotLinkSelector);
                me.setActiveDot(me._slideIndex);
            }

            me.trackItems();

            if (opts.arrowControls) {
                me.createArrows();
            }

            if (opts.thumbnails) {
                me.trackThumbnailControls();
                me.setActiveThumbnail(me._slideIndex);
            }

            me.setIndex(me._slideIndex);

            /**
             * Whether or not the user is grabbing the image with the mouse.
             *
             * @private
             * @property _grabImage
             * @type {Boolean}
             */
            me._grabImage = false;

            /**
             * First touch point position from touchstart event.
             * Will be used to determine the swiping gesture.
             *
             * @private
             * @property _startTouchPoint
             * @type {Vector}
             */
            me._startTouchPoint = new Vector(0, 0);

            /**
             * Translation (positioning) of the current image.
             *
             * @private
             * @property _imageTranslation
             * @type {Vector}
             */
            me._imageTranslation = new Vector(0, 0);

            /**
             * Scaling (both X and Y equally) of the current image.
             *
             * @private
             * @property _imageScale
             * @type {Number}
             */
            me._imageScale = 1;

            /**
             * Relative distance when pinching.
             * Will be used for the pinch to zoom gesture.
             *
             * @private
             * @property _touchDistance
             * @type {Number}
             */
            me._touchDistance = 0;

            /**
             * Last time the current image was touched.
             * Used to determine double tapping.
             *
             * @private
             * @property _lastTouchTime
             * @type {Number}
             */
            me._lastTouchTime = 0;

            /**
             * Last time the current image was touched.
             * Used to determine a swipe instead of a pull.
             *
             * @private
             * @property _lastMoveTime
             * @type {Number}
             */
            me._lastMoveTime = 0;

            /**
             * Whether or not the slider should scroll while the finger is down.
             * Used to determin if the user scrolls down to lock the horizontal
             * scrolling.
             * Gets unlocked when the user end the touch.
             *
             * @private
             * @property _lockSlide
             * @type {Boolean}
             */
            me._lockSlide = false;

            me.registerEvents();
        },

        /**
         * Registers all necessary event listeners.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this,
                opts = me.opts,
                $slide = me._$slide;

            if (opts.touchControls) {
                me._on($slide, 'touchstart mousedown', $.proxy(me.onTouchStart, me));
                me._on($slide, 'touchmove mousemove', $.proxy(me.onTouchMove, me));
                me._on($slide, 'touchend mouseup mouseleave', $.proxy(me.onTouchEnd, me));
                me._on($slide, 'MSHoldVisual', killEvent);
                me._on($slide, 'click', $.proxy(me.onClick, me));

                if (!opts.preventScrolling && ('ontouchstart' in window || navigator.msMaxTouchPoints)) {
                    me._on($slide, 'movestart', function (e) {
                        // Allows the normal up and down scrolling from the browser
                        if ((e.distX > e.distY && e.distX < -e.distY) || (e.distX < e.distY && e.distX > -e.distY)) {
                            me._lockSlide = true;
                            e.preventDefault();
                        }
                    });
                }

                if (opts.pinchToZoom) {
                    me._on($slide, 'mousewheel DOMMouseScroll scroll', $.proxy(me.onScroll, me));
                }

                if (opts.doubleTap) {
                    me._on($slide, 'dblclick', $.proxy(me.onDoubleClick, me));
                }
            }

            if (opts.arrowControls) {
                me._on(me._$arrowLeft, 'click touchstart', $.proxy(me.onLeftArrowClick, me));
                me._on(me._$arrowRight, 'click touchstart', $.proxy(me.onRightArrowClick, me));
            }

            if (opts.thumbnails) {
                me._$thumbnails.each($.proxy(me.applyClickEventHandler, me));

                me._on(me._$thumbnailArrowPrev, 'click touchstart', $.proxy(me.onThumbnailPrevArrowClick, me));
                me._on(me._$thumbnailArrowNext, 'click touchstart', $.proxy(me.onThumbnailNextArrowClick, me));

                if (opts.touchControls) {
                    me._on(me._$thumbnailSlide, 'touchstart', $.proxy(me.onThumbnailSlideTouch, me));
                    me._on(me._$thumbnailSlide, 'touchmove', $.proxy(me.onThumbnailSlideMove, me));
                }
            }

            if (opts.dotNavigation && me._$dots) {
                me._$dots.each($.proxy(me.applyClickEventHandler, me));
            }

            if (opts.autoSlide) {
                me.startAutoSlide();

                me._on(me.$el, 'mouseenter', $.proxy(me.stopAutoSlide, me));
                me._on(me.$el, 'mouseleave', $.proxy(me.startAutoSlide, me));
            }

            StateManager.on('resize', me.onResize, me);

            $.publish('plugin/swImageSlider/onRegisterEvents', [ me ]);
        },

        /**
         * Will be called when the user starts touching the image slider.
         * Checks if the user is double tapping the image.
         *
         * @event onTouchStart
         * @param {jQuery.Event} event
         */
        onTouchStart: function (event) {
            var me = this,
                opts = me.opts,
                pointers = me.getPointers(event),
                pointerA = pointers[0],
                currTime = Date.now(),
                startPoint = me._startTouchPoint,
                startX = startPoint.x,
                startY = startPoint.y,
                distance,
                deltaX,
                deltaY;

            startPoint.set(pointerA.clientX, pointerA.clientY);

            if (pointers.length === 1) {
                me._lastMoveTime = currTime;

                if (opts.autoSlide) {
                    me.stopAutoSlide();
                }

                if (event.originalEvent instanceof MouseEvent) {
                    event.preventDefault();

                    me._grabImage = true;
                    me._$slideContainer.addClass(opts.dragClass);
                    return;
                }

                if (!opts.doubleTap) {
                    return;
                }

                deltaX = Math.abs(pointerA.clientX - startX);
                deltaY = Math.abs(pointerA.clientY - startY);

                distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

                if (currTime - me._lastTouchTime < opts.doubleTapPeriod && distance <= opts.moveTolerance) {
                    me.onDoubleClick(event);
                    return;
                }

                me._lastTouchTime = currTime;
            } else {
                event.preventDefault();
            }
        },

        /**
         * Will be called when the user is moving the finger while touching
         * the image slider.
         *
         * When only one finger is touching the screen
         * and the image was scaled, it will be translated (moved).
         *
         * If two fingers are available, the image will be zoomed (pinch to zoom).
         *
         * @event onTouchMove
         * @param {jQuery.Event} event
         */
        onTouchMove: function (event) {
            var me = this,
                opts = me.opts,
                touches = me.getPointers(event),
                touchA = touches[0],
                touchB = touches[1],
                scale = me._imageScale,
                startTouch = me._startTouchPoint,
                touchDistance = me._touchDistance,
                slideStyle = me._$slide[0].style,
                percentage,
                offset,
                distance,
                deltaX,
                deltaY;

            if (touches.length > 2) {
                return;
            }

            if (touches.length === 1) {
                if (event.originalEvent instanceof MouseEvent && !me._grabImage) {
                    return;
                }

                deltaX = touchA.clientX - startTouch.x;
                deltaY = touchA.clientY - startTouch.y;

                if (scale === 1) {
                    if (me._lockSlide) {
                        return;
                    }

                    offset = (me._slideIndex * -100);
                    percentage = (deltaX / me._$slide.width()) * 100;

                    if (me._slideIndex === 0 && deltaX > 0) {
                        percentage *= Math.atan(percentage) / Math.PI;
                    }

                    if (me._slideIndex === me._itemCount - 1 && deltaX < 0) {
                        percentage *= Math.atan(percentage) / -Math.PI;
                    }

                    if (transitionProperty && transformProperty) {
                        slideStyle[transitionProperty] = 'none';
                        slideStyle[transformProperty] = 'translateX(' + (offset + percentage) + '%)';
                    } else {
                        slideStyle.left = (offset + percentage) + '%';
                    }

                    if (opts.preventScrolling) {
                        event.preventDefault();
                    }
                    return;
                }

                // If the image is zoomed, move it
                startTouch.set(touchA.clientX, touchA.clientY);

                me.translate(deltaX / scale, deltaY / scale);

                event.preventDefault();
                return;
            }

            if (!opts.pinchToZoom || !touchB) {
                return;
            }

            deltaX = Math.abs(touchA.clientX - touchB.clientX);
            deltaY = Math.abs(touchA.clientY - touchB.clientY);

            distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

            if (touchDistance === 0) {
                me._touchDistance = distance;
                return;
            }

            me.scale((distance - touchDistance) / 100);

            me._touchDistance = distance;
        },

        /**
         * Will be called when the user ends touching the image slider.
         * If the swipeToSlide option is active and the swipe tolerance is
         * exceeded, it will slide to the previous / next image.
         *
         * @event onTouchEnd
         * @param {jQuery.Event} event
         */
        onTouchEnd: function (event) {
            var me = this,
                opts = me.opts,
                touches = event.changedTouches,
                remaining = event.originalEvent.touches,
                touchA = (touches && touches[0]) || event.originalEvent,
                touchB = remaining && remaining[0],
                swipeTolerance = opts.swipeTolerance,
                pullTolerance = (typeof opts.pullTolerance === 'number') ? opts.pullTolerance : me._$slide.width() / 3,
                startPoint = me._startTouchPoint,
                deltaX,
                deltaY,
                absX,
                absY,
                swipeValid,
                pullValid;

            if (event.originalEvent instanceof MouseEvent && !me._grabImage) {
                return;
            }

            me._touchDistance = 0;
            me._grabImage = false;
            me._$slideContainer.removeClass(opts.dragClass);
            me._lockSlide = false;

            if (touchB) {
                startPoint.set(touchB.clientX, touchB.clientY);
                return;
            }

            if (opts.autoSlide) {
                me.startAutoSlide();
            }

            if (!opts.swipeToSlide || me._imageScale > 1) {
                return;
            }

            deltaX = startPoint.x - touchA.clientX;
            deltaY = startPoint.y - touchA.clientY;
            absX = Math.abs(deltaX);
            absY = Math.abs(deltaY);

            swipeValid = (Date.now() - me._lastMoveTime) < opts.swipePeriod && absX > swipeTolerance && absY < swipeTolerance;
            pullValid = (absX >= pullTolerance);

            if (Math.sqrt(deltaX * deltaX + deltaY * deltaY) > opts.moveTolerance) {
                event.preventDefault();
            }

            if (pullValid || swipeValid) {
                (deltaX < 0) ? me.slidePrev() : me.slideNext();
                return;
            }

            me.slide(me._slideIndex);
        },

        /**
         * Will be called when the user clicks on the slide.
         * This event will cancel its bubbling when the move tolerance
         * was exceeded.
         *
         * @event onClick
         * @param {jQuery.Event} event
         */
        onClick: function (event) {
            var me = this,
                opts = me.opts,
                touches = event.changedTouches,
                touchA = (touches && touches[0]) || event.originalEvent,
                startPoint = me._startTouchPoint,
                deltaX = startPoint.x - touchA.clientX,
                deltaY = startPoint.y - touchA.clientY;

            if (Math.sqrt(deltaX * deltaX + deltaY * deltaY) > opts.moveTolerance) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }

            $.publish('plugin/swImageSlider/onClick', [ me, event ]);
        },

        /**
         * Will be called when the user scrolls the image by the mouse.
         * Zooms the image in/out by the factor 0.25.
         *
         * @event onScroll
         * @param {jQuery.Event} event
         */
        onScroll: function (event) {
            var me = this,
                e = event.originalEvent;

            if ((e.detail ? e.detail * -1 : e.wheelDelta) > 0) {
                me.scale(0.25);
            } else {
                me.scale(-0.25);
            }

            event.preventDefault();

            $.publish('plugin/swImageSlider/onScroll', [ me, event ]);
        },

        /**
         * Will be called when the user
         * double clicks or double taps on the image slider.
         * When the image was scaled, it will reset its scaling
         * otherwise it will zoom in by the factor of 1.
         *
         * @event onDoubleClick
         * @param {jQuery.Event} event
         */
        onDoubleClick: function (event) {
            var me = this;

            if (!me.opts.doubleTap) {
                return;
            }

            event.preventDefault();

            if (me._imageScale <= 1) {
                me.scale(1, true);
            } else {
                me.setScale(1, true);
            }

            $.publish('plugin/swImageSlider/onDoubleClick', [ me, event ]);
        },

        /**
         * Is triggered when the left arrow
         * of the image slider is clicked or tapped.
         *
         * @event onLeftArrowClick
         * @param {jQuery.Event} event
         */
        onLeftArrowClick: function (event) {
            var me = this;

            event.preventDefault();

            me.slidePrev();

            $.publish('plugin/swImageSlider/onLeftArrowClick', [ me, event ]);
        },

        /**
         * Is triggered when the right arrow
         * of the image slider is clicked or tapped.
         *
         * @event onRightArrowClick
         * @param {jQuery.Event} event
         */
        onRightArrowClick: function (event) {
            var me = this;

            event.preventDefault();

            me.slideNext();

            $.publish('plugin/swImageSlider/onRightArrowClick', [ me, event ]);
        },

        /**
         * Slides the thumbnail slider one position backwards.
         *
         * @event onThumbnailPrevArrowClick
         * @param {jQuery.Event} event
         */
        onThumbnailPrevArrowClick: function (event) {
            event.preventDefault();

            var me = this,
                $container = me._$thumbnailContainer,
                size = me._thumbnailOrientation === 'horizontal' ? $container.innerWidth() : $container.innerHeight();

            me.setThumbnailSlidePosition(me._thumbnailOffset + (size / 2), true);
        },

        /**
         * Slides the thumbnail slider one position forward.
         *
         * @event onThumbnailNextArrowClick
         * @param {jQuery.Event} event
         */
        onThumbnailNextArrowClick: function (event) {
            event.preventDefault();

            var me = this,
                $container = me._$thumbnailContainer,
                size = me._thumbnailOrientation === 'horizontal' ? $container.innerWidth() : $container.innerHeight();

            me.setThumbnailSlidePosition(me._thumbnailOffset - (size / 2), true);

            $.publish('plugin/swImageSlider/onThumbnailNextArrowClick', [ me, event ]);
        },

        /**
         * Will be called when the user leaves the image slide with the mouse.
         * Resets the cursor grab indicator.
         *
         * @event onMouseLeave
         */
        onMouseLeave: function (event) {
            var me = this;

            me._grabImage = false;
            me._$slideContainer.removeClass(me.opts.dragClass);

            me.slide(me._slideIndex);

            $.publish('plugin/swImageSlider/onMouseLeave', [ me, event ]);
        },

        /**
         * Will be called when the viewport has been resized.
         * When thumbnails are enabled, the trackThumbnailControls function
         * will be called.
         *
         * @event onResize
         */
        onResize: function (newWidth) {
            var me = this;

            me.updateMaxZoomValue();

            me.scale(0);
            me.translate(0, 0);

            if (me.opts.thumbnails) {
                me.trackThumbnailControls();
            }

            $.publish('plugin/swImageSlider/onResize', [ me, newWidth ]);
        },

        /**
         * Will be called when the user starts touching the thumbnails slider.
         *
         * @event onThumbnailSlideTouch
         * @param {jQuery.Event} event
         */
        onThumbnailSlideTouch: function (event) {
            var me = this,
                pointers = me.getPointers(event),
                pointerA = pointers[0];

            me._startTouchPoint.set(pointerA.clientX, pointerA.clientY);

            $.publish('plugin/swImageSlider/onThumbnailSlideTouch', [ me, event, pointerA.clientX, pointerA.clientY ]);
        },

        /**
         * Will be called when the user is moving the finger while touching
         * the thumbnail slider.
         * Slides the thumbnails slider to the left/right depending on the user.
         *
         * @event onThumbnailSlideMove
         * @param {jQuery.Event} event
         */
        onThumbnailSlideMove: function (event) {
            event.preventDefault();

            var me = this,
                pointers = me.getPointers(event),
                pointerA = pointers[0],
                startPoint = me._startTouchPoint,
                isHorizontal = me._thumbnailOrientation === 'horizontal',
                posA = isHorizontal ? pointerA.clientX : pointerA.clientY,
                posB = isHorizontal ? startPoint.x : startPoint.y,
                delta = posA - posB;

            startPoint.set(pointerA.clientX, pointerA.clientY);

            me.setThumbnailSlidePosition(me._thumbnailOffset + delta, false);

            me.trackThumbnailControls();

            $.publish('plugin/swImageSlider/onThumbnailSlideTouch', [ me, event, pointerA.clientX, pointerA.clientY ]);
        },

        /**
         * Returns either an array of touches or a single mouse event.
         * This is a helper function to unify the touch/mouse gesture logic.
         *
         * @private
         * @method getPointers
         * @param {jQuery.Event} event
         */
        getPointers: function (event) {
            var origEvent = event.originalEvent || event;

            return origEvent.touches || [origEvent];
        },

        /**
         * Calculates the new x/y coordinates for the image based by the
         * given scale value.
         *
         * @private
         * @method getTransformedPosition
         * @param {Number} x
         * @param {Number} y
         * @param {Number} scale
         */
        getTransformedPosition: function (x, y, scale) {
            var me = this,
                $image = me._$currentImage,
                $container = me._$slideContainer,
                minX = Math.max(0, (($image.width() * scale - $container.width()) / scale) / 2),
                minY = Math.max(0, (($image.height() * scale - $container.height()) / scale) / 2),
                newPos = new Vector(
                    Math.max(minX * -1, Math.min(minX, x)),
                    Math.max(minY * -1, Math.min(minY, y))
                );

            $.publish('plugin/swImageSlider/onGetTransformedPosition', [ me, newPos, x, y, scale ]);

            return newPos;
        },

        /**
         * Returns the minimum possible zoom factor.
         *
         * @public
         * @method getMinScale
         * @returns {Number}
         */
        getMinScale: function () {
            return this._minZoom;
        },

        /**
         * Returns the maximum possible zoom factor.
         *
         * @public
         * @method getMaxScale
         * @returns {Number}
         */
        getMaxScale: function () {
            return this._maxZoom;
        },

        /**
         * Sets the translation (position) of the current image.
         *
         * @public
         * @method setTranslation
         * @param {Number} x
         * @param {Number} y
         */
        setTranslation: function (x, y) {
            var me = this,
                newPos = me.getTransformedPosition(x, y, me._imageScale);

            me._imageTranslation.set(newPos.x, newPos.y);

            me.updateTransform(false);

            $.publish('plugin/swImageSlider/onSetTranslation', [ me, x, y ]);
        },

        /**
         * Translates the current image relative to the current position.
         * The x/y values will be added together.
         *
         * @public
         * @method translate
         * @param {Number} x
         * @param {Number} y
         */
        translate: function (x, y) {
            var me = this,
                translation = me._imageTranslation;

            me.setTranslation(translation.x + x, translation.y + y);

            $.publish('plugin/swImageSlider/onTranslate', [ me, x, y ]);
        },

        /**
         * Scales the current image to the given scale value.
         * You can also pass the option if it should be animated
         * and if so, you can also pass a callback.
         *
         * @public
         * @method setScale
         * @param {Number|String} scale
         * @param {Boolean} animate
         * @param {Function} callback
         */
        setScale: function (scale, animate, callback) {
            var me = this,
                oldScale = me._imageScale;

            me.updateMaxZoomValue();

            me._imageScale = Math.max(me._minZoom, Math.min(me._maxZoom, scale));

            if (me._imageScale === oldScale) {
                if (typeof callback === 'function') {
                    callback.call(me);
                }
                return;
            }

            me.updateTransform(animate, callback);

            $.publish('plugin/swImageSlider/onSetScale', [ me, scale, animate, callback ]);
        },

        /**
         * Returns the current image scaling.
         *
         * @public
         * @method getScale
         * @returns {Number}
         */
        getScale: function () {
            return this._imageScale;
        },

        /**
         * Scales the current image relative to the current scale value.
         * The factor value will be added to the current scale.
         *
         * @public
         * @method scale
         * @param {Number} factor
         * @param {Boolean} animate
         * @param {Function} callback
         */
        scale: function (factor, animate, callback) {
            var me = this;

            me.setScale(me._imageScale + factor, animate, callback);

            $.publish('plugin/swImageSlider/onScale', [ me, factor, animate, callback ]);
        },

        /**
         * Updates the transformation of the current image.
         * The scale and translation will be considered into this.
         * You can also decide if the update should be animated
         * and if so, you can provide a callback function
         *
         * @public
         * @method updateTransform
         * @param {Boolean} animate
         * @param {Function} callback
         */
        updateTransform: function (animate, callback) {
            var me = this,
                translation = me._imageTranslation,
                scale = me._imageScale,
                newPosition = me.getTransformedPosition(translation.x, translation.y, scale),
                image = me._$currentImage[0],
                animationSpeed = me.opts.animationSpeed;

            translation.set(newPosition.x, newPosition.y);

            image.style[transitionProperty] = animate ? ('all ' + animationSpeed + 'ms') : '';

            image.style[transformProperty] = 'scale(' + scale + ') translate(' + translation.x + 'px, ' + translation.y + 'px)';

            $.publish('plugin/swImageSlider/onUpdateTransform', [ me, animate, callback ]);

            if (!callback) {
                return;
            }

            if (!animate) {
                callback.call(me);
                return;
            }

            setTimeout($.proxy(callback, me), animationSpeed);
        },

        /**
         * Applies a click event handler to the element
         * to slide the slider to the index of that element.
         *
         * @private
         * @method applyClickEventHandler
         * @param {Number} index
         * @param {HTMLElement} el
         */
        applyClickEventHandler: function (index, el) {
            var me = this,
                $el = $(el),
                i = index || $el.index();

            me._on($el, 'click', function (event) {
                event.preventDefault();
                me.slide(i);
            });

            $.publish('plugin/swImageSlider/onApplyClickEventHandler', [ me, index, el ]);
        },

        /**
         * Creates the arrow controls for the image slider.
         *
         * @private
         * @method createArrows
         */
        createArrows: function () {
            var me = this,
                opts = me.opts,
                hiddenClass = ' ' + opts.hiddenClass;

            /**
             * Left slide arrow element.
             *
             * @private
             * @property _$arrowLeft
             * @type {jQuery}
             */
            me._$arrowLeft = $('<a>', {
                'class': opts.leftArrowCls + ((opts.loopSlides || me._slideIndex > 0) && me._itemCount > 1 ? '' : hiddenClass)
            }).appendTo(me._$slideContainer);

            /**
             * Right slide arrow element.
             *
             * @private
             * @property _$arrowRight
             * @type {jQuery}
             */
            me._$arrowRight = $('<a>', {
                'class': opts.rightArrowCls + ((opts.loopSlides || me._slideIndex < me._itemCount - 1) && me._itemCount > 1 ? '' : hiddenClass)
            }).appendTo(me._$slideContainer);

            $.publish('plugin/swImageSlider/onCreateArrows', [ me, me._$arrowLeft, me._$arrowRight ]);
        },

        /**
         * Creates the thumbnail arrow controls for the thumbnail slider.
         *
         * @private
         * @method createThumbnailArrows
         */
        createThumbnailArrows: function () {
            var me = this,
                opts = me.opts,
                isHorizontal = (me._thumbnailOrientation === 'horizontal'),
                prevClass = isHorizontal ? opts.thumbnailArrowLeftCls : opts.thumbnailArrowTopCls,
                nextClass = isHorizontal ? opts.thumbnailArrowRightCls : opts.thumbnailArrowBottomCls;

            /**
             * Left/Top thumbnail slide arrow element.
             *
             * @private
             * @property _$thumbnailArrowPrev
             * @type {jQuery}
             */
            me._$thumbnailArrowPrev = $('<a>', {
                'class': opts.thumbnailArrowCls + ' ' + prevClass
            }).appendTo(me._$thumbnailContainer);

            /**
             * Right/Bottom thumbnail slide arrow element.
             *
             * @private
             * @property _$thumbnailArrowNext
             * @type {jQuery}
             */
            me._$thumbnailArrowNext = $('<a>', {
                'class': opts.thumbnailArrowCls + ' ' + nextClass
            }).appendTo(me._$thumbnailContainer);

            $.publish('plugin/swImageSlider/onCreateThumbnailArrows', [ me, me._$thumbnailArrowPrev, me._$thumbnailArrowNext ]);
        },

        /**
         * Tracks and counts the image elements and the thumbnail elements.
         *
         * @private
         * @method trackItems
         */
        trackItems: function () {
            var me = this,
                opts = me.opts;

            /**
             * This property contains every item in the slide.
             *
             * @private
             * @property _$items
             * @type {jQuery}
             */
            me._$items = me._$slide.find(opts.itemSelector);

            picturefill();

            /**
             * This property contains every item in the slide.
             *
             * @private
             * @property _$images
             * @type {jQuery}
             */
            me._$images = me._$slide.find(opts.imageSelector);

            if (opts.thumbnails) {
                /**
                 * Array of all thumbnail elements.
                 *
                 * @private
                 * @property _$thumbnails
                 * @type {jQuery}
                 */
                me._$thumbnails = me._$thumbnailContainer.find(opts.thumbnailSelector);

                /**
                 * Amount of all thumbnails.
                 *
                 * @private
                 * @property _thumbnailCount
                 * @type {Number}
                 */
                me._thumbnailCount = me._$thumbnails.length;

                if (me._thumbnailCount === 0) {
                    me.$el.addClass(opts.noThumbClass);
                    opts.thumbnails = false;
                }
            }

            /**
             * This property contains every item in the slide.
             *
             * @private
             * @property _itemCount
             * @type {jQuery}
             */
            me._itemCount = me._$items.length;

            $.publish('plugin/swImageSlider/onTrackItems', [ me ]);
        },

        /**
         * Sets the position of the image slide to the given image index.
         *
         * @public
         * @method setIndex
         * @param {Number} index
         */
        setIndex: function (index) {
            var me = this,
                slideStyle = me._$slide[0].style,
                percentage = ((index || me._slideIndex) * -100);

            if (transformProperty && transitionProperty) {
                slideStyle[transitionProperty] = 'none';
                slideStyle[transformProperty] = 'translateX(' + percentage + '%)';
            } else {
                slideStyle.left = percentage + '%';
            }

            me._$currentImage = $(me._$images[index]);

            me.updateMaxZoomValue();

            $.publish('plugin/swImageSlider/onSetIndex', [ me, index ]);
        },

        /**
         * Returns the current slide index.
         *
         * @public
         * @method getIndex
         * @returns {Number}
         */
        getIndex: function (event) {
            return this._slideIndex;
        },

        /**
         * Updates the max zoom factor specific to the current image.
         *
         * @private
         * @method updateMaxZoomValue
         */
        updateMaxZoomValue: function () {
            var me = this,
                $currentImage = me._$currentImage,
                image = $currentImage[0];

            if (!me._autoScale) {
                return;
            }

            if (!image) {
                me._maxZoom = me._minZoom;
                return;
            }

            me._maxZoom = Math.max(image.naturalWidth, image.naturalHeight) / Math.max($currentImage.width(), $currentImage.height());

            $.publish('plugin/swImageSlider/onUpdateMaxZoomValue', [ me, me._maxZoom ]);
        },

        /**
         * Returns the orientation of the thumbnail container.
         *
         * @private
         * @method getThumbnailOrientation
         * @returns {String}
         */
        getThumbnailOrientation: function () {
            var $container = this._$thumbnailContainer;

            return ($container.innerWidth() > $container.innerHeight()) ? 'horizontal' : 'vertical';
        },

        /**
         * Sets the active state for the thumbnail at the given index position.
         *
         * @public
         * @method setActiveThumbnail
         * @param {Number} index
         */
        setActiveThumbnail: function (index) {
            var me = this,
                isHorizontal = me._thumbnailOrientation === 'horizontal',
                orientation = isHorizontal ? 'left' : 'top',
                $thumbnail = me._$thumbnails.eq(index),
                $container = me._$thumbnailContainer,
                thumbnailPos = $thumbnail.position(),
                slidePos = me._$thumbnailSlide.position(),
                slideOffset = slidePos[orientation],
                posA = thumbnailPos[orientation] * -1,
                posB = thumbnailPos[orientation] + (isHorizontal ? $thumbnail.outerWidth() : $thumbnail.outerHeight()),
                containerSize = isHorizontal ? $container.width() : $container.height(),
                activeClass = me.opts.activeStateClass,
                newPos;

            if (posA < slideOffset && posB * -1 < slideOffset + (containerSize * -1)) {
                newPos = containerSize - Math.max(posB, containerSize);
            } else {
                newPos = Math.max(posA, slideOffset);
            }

            me._$thumbnails.removeClass(activeClass);

            $thumbnail.addClass(activeClass);

            me.setThumbnailSlidePosition(newPos, true);

            $.publish('plugin/swImageSlider/onSetActiveThumbnail', [ me, index ]);
        },

        /**
         * Sets the active state for the dot at the given index position.
         *
         * @public
         * @method setActiveDot
         * @param {Number} index
         */
        setActiveDot: function (index) {
            var me = this,
                $dots = me._$dots;

            if (me.opts.dotNavigation && $dots) {
                $dots.removeClass(me.opts.activeStateClass);
                $dots.eq(index || me._slideIndex).addClass(me.opts.activeStateClass);
            }

            $.publish('plugin/swImageSlider/onSetActiveDot', [ me, index ]);
        },

        /**
         * Sets the position of the thumbnails slider
         * If the offset exceeds the minimum/maximum position, it will be culled
         *
         * @public
         * @method setThumbnailSlidePosition
         * @param {Number} offset
         * @param {Boolean} animate
         */
        setThumbnailSlidePosition: function (offset, animate) {
            var me = this,
                $slide = me._$thumbnailSlide,
                $container = me._$thumbnailContainer,
                isHorizontal = me._thumbnailOrientation === 'horizontal',
                sizeA = isHorizontal ? $container.innerWidth() : $container.innerHeight(),
                sizeB = isHorizontal ? $slide.outerWidth(true) : $slide.outerHeight(true),
                min = Math.min(0, sizeA - sizeB),
                css = {};

            me._thumbnailOffset = Math.max(min, Math.min(0, offset));

            css[isHorizontal ? 'left' : 'top'] = me._thumbnailOffset;
            css[isHorizontal ? 'top' : 'left'] = 'auto';

            if (!animate) {
                $slide.css(css);
            } else {
                $slide[Modernizr.csstransitions ? 'transition' : 'animate'](css, me.animationSpeed, $.proxy(me.trackThumbnailControls, me));
            }

            $.publish('plugin/swImageSlider/onSetThumbnailSlidePosition', [ me, offset, animate ]);
        },

        /**
         * Checks which thumbnail arrow controls have to be shown.
         *
         * @private
         * @method trackThumbnailControls
         */
        trackThumbnailControls: function () {
            var me = this,
                opts = me.opts,
                isHorizontal = me._thumbnailOrientation === 'horizontal',
                $container = me._$thumbnailContainer,
                $slide = me._$thumbnailSlide,
                $prevArr = me._$thumbnailArrowPrev,
                $nextArr = me._$thumbnailArrowNext,
                activeCls = me.opts.activeStateClass,
                pos = $slide.position(),
                orientation = me.getThumbnailOrientation();

            if (me._thumbnailOrientation !== orientation) {
                $prevArr
                    .toggleClass(opts.thumbnailArrowLeftCls, !isHorizontal)
                    .toggleClass(opts.thumbnailArrowTopCls, isHorizontal);

                $nextArr
                    .toggleClass(opts.thumbnailArrowRightCls, !isHorizontal)
                    .toggleClass(opts.thumbnailArrowBottomCls, isHorizontal);

                me._thumbnailOrientation = orientation;

                me.setActiveThumbnail(me._slideIndex);
            }

            if (me._thumbnailOrientation === 'horizontal') {
                $prevArr.toggleClass(activeCls, pos.left < 0);
                $nextArr.toggleClass(activeCls, ($slide.innerWidth() + pos.left) > $container.innerWidth());
            } else {
                $prevArr.toggleClass(activeCls, pos.top < 0);
                $nextArr.toggleClass(activeCls, ($slide.innerHeight() + pos.top) > $container.innerHeight());
            }

            $.publish('plugin/swImageSlider/onTrackThumbnailControls', [ me ]);
        },

        /**
         * Starts the auto slide interval.
         *
         * @private
         * @method startAutoSlide
         */
        startAutoSlide: function () {
            var me = this;

            me.stopAutoSlide(me._slideInterval);

            me._slideInterval = window.setTimeout($.proxy(me.slideNext, me), me.opts.autoSlideInterval);

            $.publish('plugin/swImageSlider/onStartAutoSlide', [ me, me._slideInterval ]);
        },

        /**
         * Stops the auto slide interval.
         *
         * @private
         * @method stopAutoSlide
         */
        stopAutoSlide: function () {
            var me = this;

            window.clearTimeout(me._slideInterval);

            $.publish('plugin/swImageSlider/onStopAutoSlide', [ me ]);
        },

        /**
         * Slides the image slider to the given index position.
         *
         * @public
         * @method slide
         * @param {Number} index
         * @param {Function} callback
         */
        slide: function (index, callback) {
            var me = this,
                opts = me.opts,
                slideStyle = me._$slide[0].style;

            me._slideIndex = index;

            if (opts.thumbnails) {
                me.setActiveThumbnail(index);
                me.trackThumbnailControls();
            }

            if (opts.dotNavigation && me._$dots) {
                me.setActiveDot(index);
            }

            if (opts.autoSlide) {
                me.stopAutoSlide();
                me.startAutoSlide();
            }

            me.resetTransformation(true, function () {
                if (transitionProperty && transformProperty) {
                    slideStyle[transitionProperty] = 'all ' + opts.animationSpeed + 'ms ' + opts.animationEasing;
                    slideStyle[transformProperty] = 'translateX(' + (index * -100) + '%)';

                    if (typeof callback === 'function') {
                        setTimeout($.proxy(callback, me), opts.animationSpeed);
                    }
                } else {
                    me._$slide.animate({
                        'left': (index * -100) + '%',
                        'easing': 'ease-out'
                    }, opts.animationSpeed, $.proxy(callback, me));
                }
            });

            me._$currentImage = $(me._$images[index]);

            me.updateMaxZoomValue();

            if (opts.arrowControls) {
                me._$arrowLeft.toggleClass(opts.hiddenClass, !opts.loopSlides && index <= 0);
                me._$arrowRight.toggleClass(opts.hiddenClass, !opts.loopSlides && index >= me._itemCount - 1);
            }

            $.publish('plugin/swImageSlider/onSlide', [ me, index, callback ]);
        },

        /**
         * Resets the current image transformation (scale and translation).
         * Can also be animated.
         *
         * @public
         * @method resetTransformation
         * @param {Boolean} animate
         * @param {Function} callback
         */
        resetTransformation: function (animate, callback) {
            var me = this,
                translation = me._imageTranslation;

            me._touchDistance = 0;

            if (me._imageScale !== 1 || translation.x !== 0 || translation.y !== 0) {
                me._imageScale = 1;

                me._imageTranslation.set(0, 0);

                me.updateTransform(animate, callback);
            } else if (callback) {
                callback.call(me);
            }

            $.publish('plugin/swImageSlider/onResetTransformation', [ me, animate, callback ]);
        },

        /**
         * Slides the image slider one position forward.
         *
         * @public
         * @method slideNext
         */
        slideNext: function () {
            var me = this,
                newIndex = me._slideIndex + 1,
                itemCount = me._itemCount,
                isLooping = me.opts.loopSlides;

            me._lastTouchTime = 0;

            me.slide((newIndex >= itemCount && isLooping) ? 0 : Math.min(itemCount - 1, newIndex));

            $.publish('plugin/swImageSlider/onSlideNext', [ me, newIndex ]);
        },

        /**
         * Slides the image slider one position backwards.
         *
         * @public
         * @method slidePrev
         */
        slidePrev: function () {
            var me = this,
                newIndex = me._slideIndex - 1,
                itemCount = me._itemCount,
                isLooping = me.opts.loopSlides;

            me._lastTouchTime = 0;

            me.slide((newIndex < 0 && isLooping) ? itemCount - 1 : Math.max(0, newIndex));

            $.publish('plugin/swImageSlider/onSlidePrev', [ me, newIndex ]);
        },

        /**
         * Destroys the plugin and removes
         * all elements created by the plugin.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                opts = me.opts;

            me.resetTransformation(false);

            me._$slideContainer = null;
            me._$items = null;
            me._$currentImage = null;

            if (opts.dotNavigation && me._$dots) {
                me._$dots.removeClass(me.opts.activeStateClass);
                me._$dotNav = null;
                me._$dots = null;
            }

            if (opts.arrowControls) {
                me._$arrowLeft.remove();
                me._$arrowRight.remove();
            }

            if (opts.thumbnails) {
                me._$thumbnailArrowPrev.remove();
                me._$thumbnailArrowNext.remove();

                me._$thumbnailContainer = null;
                me._$thumbnailSlide = null;

                me._$thumbnails.removeClass(me.opts.activeStateClass);
                me._$thumbnails = null;
            }

            if (opts.autoSlide) {
                me.stopAutoSlide();
            }

            StateManager.off('resize', me.onResize, me);

            me._destroy();
        }
    });

    /**
     * Helper Class to manager coordinates of X and Y pair values.
     *
     * @class Vector
     * @constructor
     * @param {Number} x
     * @param {Number} y
     */
    function Vector(x, y) {
        var me = this;

        me.x = x || 0;
        me.y = y || 0;
    }

    /**
     * Sets the X and Y values.
     * If one of the passed parameter is not a number, it
     * will be ignored.
     *
     * @public
     * @method set
     * @param {Number} x
     * @param {Number} y
     */
    Vector.prototype.set = function (x, y) {
        var me = this;

        me.x = (typeof x === 'number') ? x : me.x;
        me.y = (typeof y === 'number') ? y : me.y;
    };
})(jQuery, Modernizr, window, Math);
