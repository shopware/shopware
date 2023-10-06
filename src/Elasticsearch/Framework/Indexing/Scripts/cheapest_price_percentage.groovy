double getPercentage(def accessors, def doc) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }

        return (double) doc[key].value;
    }

    return 0;
}

return getPercentage(params['accessors'], doc);
