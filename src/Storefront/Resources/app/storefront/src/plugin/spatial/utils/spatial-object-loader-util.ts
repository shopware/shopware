import type { Object3D } from 'three';
import type { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader';
import type { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 *
 * util class for loading spatial objects
 */
export default class SpatialObjectLoaderUtil {
    private gltfLoader: GLTFLoader;
    private readonly loadStatus: Map<string, number>;
    private $emitter?: NativeEventEmitter;

    /**
     * constructor
     */
    constructor(plugin?: { $emitter: NativeEventEmitter }) {
        // eslint-disable-next-line
        this.gltfLoader = new window.threeJsAddons.GLTFLoader();
        
        // create and bind draco loader for decompression of mesh data
        // eslint-disable-next-line
        const dracoLoader: DRACOLoader = new window.threeJsAddons.DRACOLoader();
        // eslint-disable-next-line
        dracoLoader.setDecoderPath(`${window.themeAssetsPublicPath}draco/`);
        this.gltfLoader.setDRACOLoader(dracoLoader);

        this.loadStatus = new Map<string, number>();
        if (plugin?.$emitter instanceof NativeEventEmitter) {
            this.$emitter = plugin?.$emitter;
        }
    }

    /**
     * loads a single object
     * @param url
     * @param options
     * @returns {Promise<THREE.Object3D>}
     */
    public async loadSingleObjectByUrl(
        url: string,
        options: {
            center: boolean,
            clampSize: boolean,
            clampMaxSize?: { x: number, y: number, z: number },
        }
    ): Promise<Object3D> {
        this.loadStatus.set(url, 0);
        this.emitLoadingUpdate();
        let object = await new Promise<Object3D>((resolve, reject) => {
            this.gltfLoader.load(url, (gltf) => {
                this.loadStatus.set(url, 1);
                this.emitLoadingUpdate();
                resolve(gltf.scene);
            }, (xhr) => {
                this.loadStatus.set(url, xhr.loaded / xhr.total);
                this.emitLoadingUpdate();
            }, event => {
                this.loadStatus.set(url, -1);
                this.emitLoadingUpdate();
                reject(event);
            });
        });
        if (options.clampSize) {
            object = this.clampSize(object, options.clampMaxSize);
        }
        if (options.center) {
            object = this.centerObject(object);
        }
        return object;
    }

    /**
     * centers the object
     * @param object
     * @protected
     */
    protected centerObject(object: Object3D): Object3D {
        /* eslint-disable */
        const box = new window.threeJs.Box3().setFromObject(object);
        const cent = box.getCenter(new window.threeJs.Vector3());
        
        object.position.copy(cent);
        object.position.multiplyScalar(-1);
        
        const group = new window.threeJs.Group();
        group.name = 'centered';
        group.add(object);
        return group;
        /* eslint-enable */
    }

    /**
     * clamps the size of the object to max 1x1x1
     * @param object
     * @param maxSize
     * @protected
     */
    protected clampSize(object: Object3D, maxSize: {x: number, y: number, z: number} = { x: 1, y: 1, z: 1 }): Object3D {
        // eslint-disable-next-line
        const box = new window.threeJs.Box3().setFromObject(object);
        // eslint-disable-next-line
        const size = box.getSize(new window.threeJs.Vector3());
        // eslint-disable-next-line
        const max = Math.max(size.x * (1 / maxSize.x), size.y * (1 / maxSize.y), size.z * (1 / maxSize.z));
        object.scale.multiplyScalar(1.0 / max);

        return object;
    }


    /**
     * returns the loading percentage of all objects
     * @returns {number}
     */
    public percentageLoaded(): number {
        let total = 0;
        let loaded = 0;
        this.loadStatus.forEach((value) => {
            loaded += value;
            total++;
        });
        return loaded / total;
    }

    /**
     * returns the loading status of all objects
     * @returns {Map<string, number>}
     */
    public detailLoaded(): Map<string, number> {
        return this.loadStatus;
    }

    /**
     * triggers the loading update callback
     */
    protected emitLoadingUpdate() {
        if (!this.$emitter) {
            return;
        }
        this.$emitter.publish('ObjectLoaderUtil/loadingUpdate', {
            percentage: this.percentageLoaded(),
            detailed: this.detailLoaded(),
        });
    }
}
