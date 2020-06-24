ELASTICSEARCH=$1

docker pull ${ELASTICSEARCH}
docker run -it --name=elasticsearch -e "discovery.type=single-node" -d -p 9200:9200 ${ELASTICSEARCH}
