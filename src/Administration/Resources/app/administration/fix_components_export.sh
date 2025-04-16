#!/bin/bash

find src/app/component -name "index.js" -o -name "index.ts" | while read -r file; do
  if grep -q "Component\.register(" "$file"; then
    echo "Fixing $file"
    
    # Replace the Component.register pattern at the beginning
    sed -i "" -E "s/Component\.register\(.[^{]+\{/export default {/" "$file"
    
    # Get the last line of the file
    last_line=$(tail -n 1 "$file")
    
    # Only replace the closing pattern if the last line contains '});'
    if [[ "$last_line" =~ .*\}\)\;$ ]]; then
      # Use sed to replace only the last line
      sed -i "" -E '$s/\}\);$/\};/' "$file"
    fi
  fi
done

echo "Fix complete!"
