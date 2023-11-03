def languages = params['languages'];
def suffix = params.containsKey('suffix') ? '.' + params['suffix'] : '';

for (int i = 0; i < languages.length; i++) {
    def field_name = params['field'] + '.' + languages[i] + suffix;

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.toString().length() > 0) {
        def fieldValue = doc[field_name].value;

        return fieldValue;
    }
}

if (params['order'] == 'asc') {
    return Double.MAX_VALUE;
}

return Double.MIN_VALUE;
