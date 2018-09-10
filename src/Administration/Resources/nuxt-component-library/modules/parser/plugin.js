export default function (ctx, inject) {
    const opts = <%= serialize(options) %>;
    ctx.$filesInfo = opts.filesInfo;
    const map = new Map();

    opts.filesInfo.forEach((item) => {
        map.set(item.source.name, item);
    });

    inject('filesInfo', opts.filesInfo);
}