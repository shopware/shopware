class DeviceHelper {
    constructor() {
        // TODO: Evaluate DeviceHelper class
        this._userAgent = navigator.userAgent;
        this._windowWidth = window.innerWidth;
        this._windowHeight = window.innerHeight;
        this._devicePixelRatio = window.devicePixelRatio;
        this._screenWidth = window.screen.width;
        this._screenHeight = window.screen.height;
        this._screenOrientation = window.screen.orientation.type;
    }

    // TODO: Improve event
    resize(callback) {
        window.addEventListener('resize', callback);
        return this;
    }

    get userAgent() {
        return this._userAgent;
    }

    get windowWidth() {
        return this._windowWidth;
    }

    get windowHeight() {
        return this._windowHeight;
    }

    get devicePixelRatio() {
        return this._devicePixelRatio;
    }

    get screenWidth() {
        return this._screenWidth;
    }

    get screenHeight() {
        return this._screenHeight;
    }

    get screenOrientation() {
        return this._screenOrientation;
    }
}

export default DeviceHelper;

// export default function DeviceHelper() {
//     return {
//         getUserAgent
//     };
//
//     function getUserAgent() {
//         return navigator.userAgent;
//     }
// }
