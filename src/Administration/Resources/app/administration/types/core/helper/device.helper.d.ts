/// <reference lib="dom" />

import { VueConstructor } from 'vue';

export class DeviceHelper {
    resize(): void;

    onResize(listenerArgs: {
        listener: () => void;
        scope: any;
        component: VueConstructor;
    }): number;

    removeResizeListener(component: VueConstructor): boolean;

    getUserAgent(): Navigator['userAgent'];

    getViewportWidth(): Window['innerWidth'];

    getViewportHeight(): Window['innerHeight'];

    getDevicePixelRatio(): Window['devicePixelRatio'];

    getScreenWidth(): Screen['width'];

    getScreenHeight(): Screen['height'];

    getScreenOrientation(): ScreenOrientation;

    getBrowserLanguage(): Navigator['language'];

    getPlatform(): NavigatorID['platform'];

    getSystemKey(): string;
}
