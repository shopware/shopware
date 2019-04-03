import { tns } from 'tiny-slider/src/tiny-slider.module';

const DETAIL_MEDIA_GALLERY_DATA_ATTRIBUTE = 'data-detail-media-gallery';
const GALLERY_SLIDER_DATA_ATTRIBUTE = 'data-gallery-slider';
const GALLERY_THUMBNAILS_DATA_ATTRIBUTE = 'data-gallery-thumbnails';
const GALLERY_CONTROLS_DATA_ATTRIBUTE = 'data-gallery-controls';

export default class GallerySlider {

    /**
    * Constructor.
    */
    constructor() {
        this._init();
    }

    /**
     * Initialize Gallery Slider
     * @private
     */
    _init() {
        let galleries = document.querySelectorAll(`*[${DETAIL_MEDIA_GALLERY_DATA_ATTRIBUTE}=true]`);
        
        galleries.forEach((gallery) => {
            const gallerySlider = gallery.querySelector(`*[${GALLERY_SLIDER_DATA_ATTRIBUTE}=true]`);
            const galleryThumbnails = gallery.querySelector(`*[${GALLERY_THUMBNAILS_DATA_ATTRIBUTE}=true]`);
            const galleryControls = gallery.querySelector(`*[${GALLERY_CONTROLS_DATA_ATTRIBUTE}=true]`);

            tns({
                container: gallerySlider,
                items: 1,
                mode: 'gallery',
                controlsContainer: galleryControls,
                navContainer: galleryThumbnails,
                navAsThumbnails: true
            });
        });
    }
}