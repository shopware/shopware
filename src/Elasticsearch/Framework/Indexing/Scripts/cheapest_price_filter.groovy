double getPrice(def accessors, def doc, def decimals, def round, def multiplier) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }

        def factor = accessor['factor'];
        def value = doc[key].value * factor;

        value = Math.round(value * decimals);
        value = (double) value / decimals;

        if (!round) {
            return (double) value;
        }

        value = Math.round(value * multiplier);

        value = (double) value / multiplier;

        return (double) value;
    }

    return 0;
}

def price = getPrice(params['accessors'], doc, params['decimals'], params['round'], params['multiplier']);

def match = true;
if (params.containsKey('gte')) {
    match = match && price >= params['gte'];
}
if (params.containsKey('gt')) {
    match = match && price > params['gt'];
}
if (params.containsKey('lte')) {
    match = match && price <= params['lte'];
}
if (params.containsKey('lt')) {
    match = match && price < params['lt'];
}

return match;
