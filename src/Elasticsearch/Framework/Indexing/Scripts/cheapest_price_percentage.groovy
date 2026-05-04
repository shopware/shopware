double getRatio(def accessors, def doc) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }

        return 100 - (double) doc[key].value;
    }

    return 0;
}

return getRatio(params['accessors'], doc);
