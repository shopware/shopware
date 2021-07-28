export interface WorkerNotificationDefinition {
    name: string;
    fn: (error: null, next: () => void, mergedData: object) => void;
}
