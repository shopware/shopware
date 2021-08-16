type Flags = {
    [key: string]: boolean;
};

export interface Feature {
    init(flagConfig: Flags): void;

    getAll(): Flags;

    isActive(flagName: string): boolean;
}
