export interface FlatTreeNode {
    color?: string;
    icon?: string;
    id?: string;
    label?: string;
    level: number;
    moduleType: 'core' | 'plugin';
    path?: string;
    positon: number;
}

export class FlatTreeHelper {
    constructor(sorting?: () => number, defaultPosition?: number);

    convertToTree(): FlatTreeNode[];

    add(node: FlatTreeNode): FlatTreeHelper;

    remove(nodeIdentifier: string): FlatTreeHelper;

    getRegisteredNodes(): Record<string, FlatTreeNode>;

    set defaultPosition(value: number);

    get defaultPosition(): number;
}
