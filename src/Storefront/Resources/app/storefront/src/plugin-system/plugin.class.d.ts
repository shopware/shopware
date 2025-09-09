export default class Plugin {
    el: Node;
    $emitter: any;
    options: any;
    _pluginName: string;
    _initialized: boolean;

    constructor(el: Node, options?: any, pluginName?: string | false);
    init(): void;
    update(): void;
}
