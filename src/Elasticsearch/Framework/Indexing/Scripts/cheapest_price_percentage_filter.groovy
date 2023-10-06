String getPercentageKey(def accessors, def doc) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }

        return key;
    }

    return '';
}

def percentageKey = getPercentageKey(params['accessors'], doc);

if (percentageKey == '') {
    return false;
}

def percentage = (double) doc[percentageKey].value;

def match = true;
if (params.containsKey('gte')) {
    match = match && percentage >= params['gte'];
}
if (params.containsKey('gt')) {
    match = match && percentage > params['gt'];
}
if (params.containsKey('lte')) {
    match = match && percentage <= params['lte'];
}
if (params.containsKey('lt')) {
    match = match && percentage < params['lt'];
}

return match;
