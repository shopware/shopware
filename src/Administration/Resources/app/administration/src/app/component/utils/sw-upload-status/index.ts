import { UploadEvents } from 'src/core/service/api/media.api.service';
import { type Snackbar, useSnackbar } from '@shopware-ag/meteor-component-library';
import type MediaApiService from 'src/core/service/api/media.api.service';

const UploadStatus = {
    ACTIVE: 'active',
    FAILED: 'failed',
    PENDING: 'pending',
    FINISHED: 'finished',
};

type FileInfo = {
    size: number;
    uploaded: number;
    name: string;
    targetId: string;
    status: (typeof UploadStatus)[keyof typeof UploadStatus];
};

type ApiError = {
    code: string;
    status: string;
    title: string;
    detail: string;
};

type UploadError = {
    code?: string;
    message?: string;
    response?: {
        status?: number;
        data?: {
            errors?: ApiError[];
        };
    };
};

type UploadTask = {
    targetId: string;
    src: File;
};

type UploadFailedPayload = {
    targetId: string;
    fileName: string;
    error: UploadError;
};

type MediaUploadAction = (typeof UploadEvents)[keyof typeof UploadEvents];

type MediaUploadPayload = {
    data?: unknown;
    loaded?: number;
    total?: number;
    targetId?: string;
    originalTargetId?: string | null;
    fileName?: string;
    error?: UploadError;
};

type MediaUploadEvent = {
    action: MediaUploadAction;
    payload: MediaUploadPayload;
    uploadTag: string;
};

const ResponseErrorCodes = {
    ILLEGAL_FILE_NAME: 'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
    ILLEGAL_URL: 'CONTENT__MEDIA_ILLEGAL_URL',
    ILLEGAL_FILE_TYPE: 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED',
    DUPLICATED_FILE_NAME: 'CONTENT__MEDIA_DUPLICATED_FILE_NAME',
} as const;

const ClientErrorCodes = {
    REQUEST_TIMEOUT: 'ECONNABORTED',
    REQUEST_CANCELED: 'ERR_CANCELED',
} as const;

const IgnoredErrors = [
    ResponseErrorCodes.DUPLICATED_FILE_NAME, // Handled by sw-duplicated-media-v2
] as const;

const ErrorMessages = {
    [ResponseErrorCodes.ILLEGAL_FILE_NAME]: 'global.sw-media-upload.notification.illegalFilename.message',
    [ResponseErrorCodes.ILLEGAL_URL]: 'global.sw-media-upload.notification.illegalFileUrl.message',
    [ResponseErrorCodes.ILLEGAL_FILE_TYPE]: 'global.sw-media-upload.notification.fileTypeNotSupported.message',
    [ClientErrorCodes.REQUEST_TIMEOUT]: 'global.sw-media-upload.notification.transportError.message',
    [ClientErrorCodes.REQUEST_CANCELED]: 'global.sw-media-upload.notification.requestCanceled.message',
} as const;

const StatusMessages = {
    PAYLOAD_TOO_LARGE: 'global.sw-media-upload.notification.payloadTooLarge.message',
    TRANSPORT_ERROR: 'global.sw-media-upload.notification.transportError.message',
} as const;

const TimeoutStatuses = [
    408, // Origin request timed out before the server responded
    504, // Upstream gateway timed out waiting for response
    524, // Proxy timed out waiting for origin
];
const GatewayErrorStatuses = [
    502, // Bad gateway from upstream server
    503, // Service unavailable on upstream server
];

/**
 * This component listens to media upload events and shows a snackbar displaying the upload progress.
 * If a file upload fails, an additional notification with more details is displayed.
 *
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template: '<slot />',
    inject: [
        'mediaService',
    ],
    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],
    data() {
        return {
            uploads: new Map<string, FileInfo>(),
            snackbarItem: null as Snackbar | null,
        };
    },
    computed: {
        snackbar() {
            return useSnackbar();
        },
        uploadCount() {
            return this.uploads.size;
        },
        hasFailedUploads() {
            return Array.from(this.uploads.values()).some((fileInfo) => fileInfo.status === UploadStatus.FAILED);
        },
        uploadProgress() {
            let total = 0;
            let uploaded = 0;

            this.uploads.forEach((fileInfo) => {
                total += fileInfo.size;
                if (fileInfo.status === UploadStatus.FINISHED || fileInfo.status === UploadStatus.FAILED) {
                    uploaded += fileInfo.size;
                    return;
                }

                uploaded += Math.min(fileInfo.uploaded, fileInfo.size);
            });

            if (total === 0) {
                return 0;
            }

            return Math.round((uploaded / total) * 100);
        },
        processedUploadCount() {
            return Array.from(this.uploads.values()).filter((fileInfo) => {
                return fileInfo.status === UploadStatus.FINISHED || fileInfo.status === UploadStatus.FAILED;
            }).length;
        },
        uploadComplete() {
            return this.uploadCount > 0 && this.processedUploadCount === this.uploadCount;
        },
        snackbarMessage(): string {
            const { uploadCount, uploadProgress, processedUploadCount } = this;

            return this.$t('global.sw-media-upload.snackbar.message', {
                count: uploadCount,
                progress: uploadProgress,
                processed: processedUploadCount,
                total: uploadCount,
            });
        },
        snackbarConfig(): Snackbar {
            const { uploadCount, uploadProgress } = this;

            const config: Snackbar = {
                id: 'media-upload-status',
                message: this.snackbarMessage,
                variant: 'progress',
                progressPercentage: uploadProgress,
                duration: 0,
            };

            if (this.uploadComplete) {
                Object.assign(config, {
                    uploadState: this.hasFailedUploads ? 'error' : 'success',
                    errorMessage: this.hasFailedUploads
                        ? this.$t('global.sw-media-upload.snackbar.errorMessage', { count: uploadCount })
                        : undefined,
                    duration: 0,
                });
            }

            return config;
        },
    },
    created() {
        this.registerListeners();
    },
    methods: {
        registerListeners() {
            (this.mediaService as MediaApiService).addDefaultListener(this.onUploadEvent.bind(this));
        },
        getUploadId(name: string, size: number): string {
            return `${name}:${size}`;
        },
        findByTargetId(targetId: string): { uploadId: string; fileInfo: FileInfo } | null {
            let found: { uploadId: string; fileInfo: FileInfo } | null = null;

            this.uploads.forEach((fileInfo, uploadId) => {
                if (fileInfo.targetId === targetId) {
                    found = { uploadId, fileInfo };
                }
            });

            return found;
        },
        onUploadEvent(event: MediaUploadEvent) {
            switch (event.action) {
                case UploadEvents.UPLOAD_ADDED:
                    this.onUploadAdded(event);
                    break;
                case UploadEvents.UPLOAD_FINISHED:
                    this.onUploadFinished(event);
                    break;
                case UploadEvents.UPLOAD_PROGRESS:
                    this.onUploadProgress(event);
                    break;
                case UploadEvents.UPLOAD_FAILED:
                    this.onUploadFailed(event);
                    break;
                case UploadEvents.UPLOAD_CANCELED:
                    this.onUploadCancel(event);
                    break;
                default:
                    break;
            }

            this.updateSnackbar();
        },
        onUploadAdded(event: MediaUploadEvent) {
            const tasks = event.payload?.data as UploadTask[] | undefined;

            tasks?.forEach((uploadTask) => {
                const { targetId, src } = uploadTask;
                const uploadId = this.getUploadId(src.name, src.size);

                this.uploads.set(uploadId, {
                    size: src.size,
                    uploaded: 0,
                    name: src.name,
                    targetId,
                    status: UploadStatus.ACTIVE,
                });
            });
        },
        onUploadFinished(event: MediaUploadEvent) {
            const targetId = event.payload.originalTargetId ?? event.payload.targetId ?? '';
            const found = this.findByTargetId(targetId);

            if (!found) {
                return;
            }

            found.fileInfo.status = UploadStatus.FINISHED;
            found.fileInfo.uploaded = found.fileInfo.size;
            this.uploads.set(found.uploadId, found.fileInfo);
        },
        onUploadFailed(event: MediaUploadEvent) {
            const { payload } = event;
            const found = this.findByTargetId(payload.targetId ?? '');

            if (!found) {
                return;
            }

            if (
                payload?.error?.response?.data?.errors?.find(
                    (error) => error.code === ResponseErrorCodes.DUPLICATED_FILE_NAME,
                )
            ) {
                found.fileInfo.status = UploadStatus.PENDING;
            } else {
                found.fileInfo.status = UploadStatus.FAILED;
            }

            this.uploads.set(found.uploadId, found.fileInfo);
            this.showErrorNotification(payload as UploadFailedPayload);
        },
        onUploadProgress(event: MediaUploadEvent) {
            const targetId = event.payload.targetId ?? '';
            const found = this.findByTargetId(targetId);

            if (!found) {
                return;
            }

            const loaded = event.payload.loaded ?? 0;
            const total = event.payload.total ?? found.fileInfo.size;
            found.fileInfo.uploaded = Math.min(loaded, total, found.fileInfo.size);
            this.uploads.set(found.uploadId, found.fileInfo);
        },
        onUploadCancel(event: MediaUploadEvent) {
            const data = event.payload.data as { targetId?: string } | undefined;
            const targetId = data?.targetId ?? '';
            const found = this.findByTargetId(targetId);

            if (!found) {
                return;
            }

            this.uploads.delete(found.uploadId);
        },
        updateSnackbar() {
            if (this.uploadCount === 0) {
                if (this.snackbarItem) {
                    this.snackbar.removeSnackbar(this.snackbarItem.id);
                }

                this.snackbarItem = null;
                return;
            }

            if (this.snackbarItem) {
                Object.assign(this.snackbarItem, this.snackbarConfig);
            } else {
                this.snackbarItem = this.snackbar.addSnackbar(this.snackbarConfig);
            }

            if (this.uploadComplete) {
                this.snackbarItem = null;
                this.uploads.clear();
            }
        },
        showErrorNotification(payload: UploadFailedPayload) {
            const messageSnippets = [];

            if (!payload?.error?.response && ErrorMessages[payload.error?.code as keyof typeof ErrorMessages]) {
                messageSnippets.push(ErrorMessages[payload.error.code as keyof typeof ErrorMessages]);
            } else {
                payload?.error?.response?.data?.errors?.forEach((error) => {
                    if (IgnoredErrors.includes(error.code as (typeof IgnoredErrors)[number])) {
                        return;
                    }

                    const snippetKey = ErrorMessages[error.code as keyof typeof ErrorMessages];

                    if (!snippetKey) {
                        return;
                    }

                    messageSnippets.push(snippetKey);
                });
            }

            if (messageSnippets.length === 0) {
                const transportSnippet = this.getTransportErrorSnippet(payload?.error);

                if (transportSnippet) {
                    messageSnippets.push(transportSnippet);
                }
            }

            messageSnippets.forEach((snippet) => {
                this.createNotificationError({
                    message: this.$t(snippet, { fileName: payload.fileName }),
                });
            });
        },
        getTransportErrorSnippet(error?: UploadError): string | null {
            const status = error?.response?.status ?? -1;

            if (status === 413) {
                return StatusMessages.PAYLOAD_TOO_LARGE;
            }

            if (TimeoutStatuses.includes(status)) {
                return StatusMessages.TRANSPORT_ERROR;
            }

            if (GatewayErrorStatuses.includes(status)) {
                return StatusMessages.TRANSPORT_ERROR;
            }

            if (!error?.response && error?.message === 'Network Error') {
                return StatusMessages.TRANSPORT_ERROR;
            }

            return null;
        },
    },
});
