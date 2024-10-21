import type { Group, Scene } from 'three';

export type Light = {
    id: string,
    type: string,
    color: string,
    intensity: number,
    position?: { x: number, y: number, z: number },
    target?: { x: number, y: number, z: number },
}

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class SpatialLightCompositionUtil {
    static defaultLights: Light[] = [
        {
            id: 'ambient',
            type: 'ambient',
            color: '#ffffff',
            intensity: 1,
        },
        {
            id: 'directional',
            type: 'directional',
            color: '#ffffff',
            intensity: 1,
            position: { x: -5, y: 2, z: 2 },
            target: { x: 0, y: 0, z: 0 },
        },
        {
            id: 'directional',
            type: 'directional',
            color: '#ffffff',
            intensity: 1,
            position: { x: 0, y: 2, z: 2 },
            target: { x: 0, y: 0, z: 0 },
        },
        {
            id: 'directional',
            type: 'directional',
            color: '#ffffff',
            intensity: 1,
            position: { x: 5, y: 2, z: 2 },
            target: { x: 0, y: 0, z: 0 },
        },
    ];

    private lights: Light[];
    private scene: Scene;
    private lightGroup: Group;

    constructor(scene: Scene, intensity?: string, lights?: Light[]) {
        this.lights = lights ?? SpatialLightCompositionUtil.defaultLights;

        if (intensity) {
            this.lights.forEach(light => {
                light.intensity = Number(intensity) / 100;
            });
        }

        this.scene = scene;
        // eslint-disable-next-line
        this.lightGroup = new window.threeJs.Group();
        this.lightGroup.name = 'lightGroup';

        this.lights.forEach(light => {
            this.addLight(light);
        });
        this.scene.add(this.lightGroup);
    }

    /**
     * initializes one ambient light
     * @param light
     * @private
     */
    private initAmbientLight(light: Light) {
        /* eslint-disable */
        const ambientLight = new window.threeJs.AmbientLight(light.color, light.intensity);
        ambientLight.name = light.id;
        this.lightGroup.add(ambientLight);
        /* eslint-enable */
    }

    /**
     * initializes one directional light
     * @param light
     * @private
     */
    private initDirectionalLight(light: Light) {
        /* eslint-disable */
        const directionalLight = new window.threeJs.DirectionalLight(light.color, light.intensity);
        directionalLight.position.set(light.position?.x ?? 0, light.position?.y ?? 0, light.position?.z ?? 0);
        directionalLight.target.position.set(light.target?.x ?? 0, light.target?.y ?? 0, light.target?.z ?? 0);
        directionalLight.name = light.id;
        this.lightGroup.add(directionalLight);
        /* eslint-enable */
    }

    /**
     * adds a light to the scene
     * @param light
     */
    public addLight(light: Light) {
        switch (light.type) {
            case 'ambient':
                this.initAmbientLight(light);
                break;
            case 'directional':
                this.initDirectionalLight(light);
                break;
        }
    }

    /**
     * removes a light from the scene
     * @param light
     */
    public removeLight(light: Light) {
        this.removeLightById(light.id);
    }

    /**
     * removes a light from the scene by id
     * @param id
     */
    public removeLightById(id: string) {
        this.lights = this.lights.filter(light => light.id !== id);
        const l = this.lightGroup.getObjectByName(id);
        if (l) {
            this.lightGroup.remove(l);
        }
    }

    /**
     * disposes all lights
     */
    public dispose() {
        this.lights = [];
        this.scene.remove(this.lightGroup);
    }
}
