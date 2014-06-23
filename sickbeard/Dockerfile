FROM ubuntu:14.04
MAINTAINER gfjardims <gfjardim@gmail.com>

RUN usermod -u 99 nobody && \
    usermod -g 100 nobody

RUN apt-get update -q && \
    apt-get install -qy --force-yes python python-cheetah ca-certificates git supervisor

RUN git clone https://github.com/midgetspy/Sick-Beard/ /opt/sickbeard/ && \
    chown -R nobody /opt/sickbeard

VOLUME /config

EXPOSE 8081

CMD ["supervisord", "-c", "/opt/supervisor.conf", "-n"]
