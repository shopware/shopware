import deepmerge from 'deepmerge';
import { tns } from 'tiny-slider/src/tiny-slider.module';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import SliderSettingsHelper from 'src/plugin/slider/helper/slider-settings.helper';
import PluginManager from 'src/plugin-system/plugin.manager';
import Iterator from 'src/helper/iterator.helper';
import BaseSliderPlugin from 'src/plugin/slider/base-slider.plugin';
import DomAccess from 'src/helper/dom-access.helper';

export default class GallerySliderPlugin extends BaseSliderPlugin {

  /**
   * default slider options
   *
   * @type {*}
   */
  static options = deepmerge(BaseSliderPlugin.options, {
      containerSelector: '[data-gallery-slider-container=true]',
      thumbnailsSelector: '[data-gallery-slider-thumbnails=true]',
      controlsSelector: '[data-gallery-slider-controls=true]',
      thumbnailControlsSelector: '[data-thumbnail-slider-controls=true]',
      dotActiveClass: 'tns-nav-active',
      navDotDataAttr: 'data-nav-dot',
      slider: {
          startIndex: 1,
          responsive: {
              xs: {},
              sm: {},
              md: {},
              lg: {},
              xl: {},
          },
      },
      thumbnailSlider: {
          enabled: true,
          loop: false,
          nav: false,
          items: 5,
          gutter: 10,
          startIndex: 1,
          responsive: {
              xs: {},
              sm: {},
              md: {},
              lg: {},
              xl: {},
          },
      },
  });

  init() {
      this._slider = false;
      this._thumbnailSlider = false;

      if (!this.el.classList.contains(this.options.initializedCls)) {
          this.options.slider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.slider);
          this.options.thumbnailSlider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.thumbnailSlider);
          this._correctIndexSettings();

          this._getSettings(ViewportDetection.getCurrentViewport());

          this._initSlider();
          this._registerEvents();
      }
  }

  /**
   * since the tns slider indexes internally with 0
   * but the setting starts at 1 we have to subtract 1
   * to have the correct index
   *
   * @private
   */
  _correctIndexSettings() {
      super._correctIndexSettings();

      this.options.thumbnailSlider.startIndex -= 1;
      this.options.thumbnailSlider.startIndex = (this.options.thumbnailSlider.startIndex < 0) ? 0 : this.options.thumbnailSlider.startIndex;
  }

  /**
   * destroys the slider
   */
  destroy() {
      if (this._slider && typeof this._slider.destroy === 'function') {
          try {
              this._slider.destroy();
          } catch (e) {
              // don't handle
          }
      }

      if (this._thumbnailSlider && typeof this._thumbnailSlider.destroy === 'function') {
          try {
              this._thumbnailSlider.destroy();
          } catch (e) {
              // don't handle
          }
      }

      this.el.classList.remove(this.options.initializedCls);
  }

  /**
   * reinitialise the slider
   * with the options for our viewport
   *
   * @param viewport
   */
  rebuild(viewport = ViewportDetection.getCurrentViewport()) {
      this._getSettings(viewport.toLowerCase());

      // get the current index and use it as the start index
      try {
          if (this._slider) {
              const currentIndex = this.getCurrentSliderIndex();
              this._sliderSettings.startIndex = currentIndex;
              this._thumbnailSliderSettings.startIndex = currentIndex;
          }

          this.destroy();
          this._initSlider();
      } catch (e) {
      // something went wrong
      }

      this.$emitter.publish('rebuild');
  }

  /**
   * returns the slider settings for the current viewport
   *
   * @param viewport
   * @private
   */
  _getSettings(viewport) {
      super._getSettings(viewport);

      this._thumbnailSliderSettings = SliderSettingsHelper.getViewportSettings(this.options.thumbnailSlider, viewport);
  }

  /**
   * sets the active dot depending on the slider index
   *
   * @private
   */
  _setActiveDot() {
      const currentIndex = this.getCurrentSliderIndex();

      Iterator.iterate(this._dots, dot => dot.classList.remove(this.options.dotActiveClass));

      const currentDot = this._dots[currentIndex];

      if (!currentDot) return;

      currentDot.classList.add(this.options.dotActiveClass);
  }

  /**
   * initializes the dot navigation for the gallery slider
   *
   * @private
   */
  _initDots() {
      this._dots = this.el.querySelectorAll('[' + this.options.navDotDataAttr + ']');

      if (!this._dots) return;

      Iterator.iterate(this._dots, dot => {
          dot.addEventListener('click', this._onDotClick.bind(this));
      });

      this._setActiveDot();

      if (this._slider) {
          this._slider.events.on('indexChanged', () => {
              this._setActiveDot();
          });
      }
  }

  /**
   * navigates the gallery slider on dot click
   *
   * @private
   */
  _onDotClick(event) {
      const currentIndex = DomAccess.getDataAttribute(event.target, this.options.navDotDataAttr);

      this._slider.goTo(currentIndex - 1);
  }

  /**
   * initialize the slider
   *
   * @private
   */
  _initSlider() {
      this.el.classList.add(this.options.initializedCls);

      const container = this.el.querySelector(this.options.containerSelector);
      const navContainer = this.el.querySelector(this.options.thumbnailsSelector);
      const controlsContainer = this.el.querySelector(this.options.controlsSelector);

      if (container) {
          const onInit = () => {
              PluginManager.initializePlugin('Magnifier', '[data-magnifier]');
              PluginManager.initializePlugin('ZoomModal', '[data-zoom-modal]');

              this.$emitter.publish('initGallerySlider');
          };

          if (this._sliderSettings.enabled) {
              container.style.display = '';

              this._slider = tns({
                  container,
                  controlsContainer,
                  navContainer,
                  onInit,
                  ...this._sliderSettings,
              });

              this._initDots();
          } else {
              container.style.display = 'none';
          }
      }

      if (navContainer) {
          const thumbnailControls = this.el.querySelector(this.options.thumbnailControlsSelector);

          const onInitThumbnails = () => {
              this.$emitter.publish('initThumbnailSlider');
          };

          if (this._thumbnailSliderSettings.enabled) {
              navContainer.style.display = '';

              this._thumbnailSlider = tns({
                  container: navContainer,
                  controlsContainer: thumbnailControls,
                  onInit: onInitThumbnails,
                  ...this._thumbnailSliderSettings,
              });
          } else {
              navContainer.style.display = 'none';
          }
      }

      this.$emitter.publish('afterInitSlider');
  }
}
