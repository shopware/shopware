type MediaUploadResult = {
    failureAmount: number;
    successAmount: number;
    totalAmount: number;
    targetId: EntityKey<'media'>;
};

/**
 * @private
 * @sw-package discovery
 */
export default MediaUploadResult;
