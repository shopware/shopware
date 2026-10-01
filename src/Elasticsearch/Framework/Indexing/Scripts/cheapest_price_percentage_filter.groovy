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
    if (params.containsKey('eq') && params['eq'] === null) {
        return true;
    }

    return false;
}

def ratio = 100 - (double) doc[percentageKey].value;

def match = true;
if (params.containsKey('eq')) {
    match = match && ratio == params['eq'];
}
if (params.containsKey('gte')) {
    match = match && ratio >= params['gte'];
}
if (params.containsKey('gt')) {
    match = match && ratio > params['gt'];
}
if (params.containsKey('lte')) {
    match = match && ratio <= params['lte'];
}
if (params.containsKey('lt')) {
    match = match && ratio < params['lt'];
}

return match;
