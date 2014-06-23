#!/bin/bash
# Stop running containers
RUNNING=$(docker ps -q 2>/dev/null)
if [[ -n ${RUNNING} ]]; then
  docker stop ${RUNNING}
fi

# Remove all containers
ALL=$(docker ps -a -q 2>/dev/null)
if [[ -n ${ALL} ]]; then
  docker rm ${ALL}
fi
