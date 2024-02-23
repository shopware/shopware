import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import { COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS } from 'src/plugin/cookie/cookie-configuration.plugin';

export const CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO = 'CmsGdprVideoElement_replaceElementWithVideo';

/**
 * @package content
 */
export default class CmsGdprVideoElement extends Plugin {
    /**
     * Plugin options
     * @type {{btnClasses: Array<String>, videoUrl: null, iframeClasses: Array<String>, overlayText: null, backdropClass: Array<String>, confirmButtonText: null}}
     */
    static options = {
        cookieName: 'youtube-video',
        btnClasses: [],
        videoUrl: null,
        iframeClasses: [],
        overlayText: null,
        backdropClasses: ['element-loader-backdrop', 'element-loader-backdrop-open'],
        confirmButtonText: null,
        modalTriggerSelector: '[data-bs-toggle="modal"][data-url]',
        urlAttribute: 'data-url',
    };

    /**
     * Plugin initializer
     *
     * @returns {void|boolean}
     */
    init() {
        document.$emitter.subscribe(COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS, this.checkConsentAndReplaceVideo.bind(this));
        document.$emitter.subscribe(CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO, this._replaceElementWithVideo.bind(this));

        this.checkConsentAndReplaceVideo();

        this._client = new HttpClient();
        this.backdropElement = this.createElementBackdrop();
        this.el.appendChild(this.backdropElement);
    }

    /**
     * Creates an element overlay as well as the content of the overlay (e.g. text and button)
     *
     * @returns {Element}
     */
    createElementBackdrop() {
        const backdropElement = document.createElement('div');

        // Iterating over the classes for IE11 compatibility, see {@link https://caniuse.com/#feat=classlist}
        this.options.backdropClasses.forEach((cls) => {
            backdropElement.classList.add(cls);
        });

        const childWrapper = document.createElement('div');
        childWrapper.appendChild(this.createTextOverlay());
        childWrapper.appendChild(this.createBackdropConfirmElement());

        backdropElement.appendChild(childWrapper);

        return backdropElement;
    }

    /**
     * Creates a text element which outputs a privacy notice.
     *
     * @returns {Element}
     */
    createTextOverlay() {
        const paragraphElement = document.createElement('p');
        paragraphElement.innerHTML = this.options.overlayText;

        return paragraphElement;
    }

    /**
     * Creates a button element which can be triggered to replace the element backdrop with the actual video element.
     *
     * @returns {Element}
     */
    createBackdropConfirmElement() {
        const buttonElement = document.createElement('button');
        buttonElement.innerHTML = this.options.confirmButtonText;

        this.options.btnClasses.forEach((cls) => {
            buttonElement.classList.add(cls);
        });

        buttonElement.addEventListener('click', this.onReplaceElementWithVideo.bind(this), false, {
            once: true,
        });

        return buttonElement;
    }

    /**
     * Event handler listener which gets fired when the confirm element gets clicked.
     *
     * @fires click
     * @param {Event} event
     * @returns {boolean}
     */
    onReplaceElementWithVideo(event) {
        event.preventDefault();

        CookieStorageHelper.setItem(this.options.cookieName, '1', '30');

        document.$emitter.publish(CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO);

        return true;
    }

    /**
     * Execute replacing the element with video
     *
     * @returns {boolean}
     */
    _replaceElementWithVideo() {
        const videoElement = document.createElement('iframe');
        videoElement.setAttribute('src', this.options.videoUrl);

        this.options.iframeClasses.forEach((cls) => {
            videoElement.classList.add(cls);
        });

        const parentNode = this.el.parentNode;
        parentNode.appendChild(videoElement);
        parentNode.removeChild(this.el);

        return true;
    }

    /**
     * Checks for user consent for the video cookie and replaces the video element if consent is given.
     */
    checkConsentAndReplaceVideo() {
        if (CookieStorageHelper.getItem(this.options.cookieName)) {
            this._replaceElementWithVideo();
        }
    }
}
