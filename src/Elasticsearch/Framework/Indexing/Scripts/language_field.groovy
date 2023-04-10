def languages = params['languages'];

for (int i = 0; i < languages.length; i++) {
    def field_name = params['field'] + '_' + languages[i];

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.length() > 0) {
      return doc[field_name].value;
    }
}

return '';
