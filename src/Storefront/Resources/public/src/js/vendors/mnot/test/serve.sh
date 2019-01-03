#!/bin/bash

PORT=$1
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cp -f $DIR/../hinclude.js $DIR/assets/

cd $DIR/assets

echo "Starting server on $PORT" >/dev/stderr
python -m SimpleHTTPServer $PORT 2>/dev/null &
echo "PID $!"
