#!/bin/bash

/etc/rc.d/rc.docker stop

if [[ -f /etc/default/docker ]]; then
  . /etc/default/docker
  if [[ -d ${DOCKER_HOME} ]]; then
    if [[ -d ${DOCKER_HOME}/btrfs/subvolumes ]]; then
      find ${DOCKER_HOME}/btrfs/subvolumes -type d -mindepth 1 -maxdepth 1 -exec btrfs subvolume delete '{}' \;
    fi
    rm -rf ${DOCKER_HOME}
  fi
fi

