#!/bin/sh

CWD=`pwd`
cd `dirname "$0"`
CMEDIR=`pwd`
cd "$CWD"

apt-get update
apt-get -y install monit php5-cli php5-mysqlnd php5-curl php5-mcrypt curl

#install composer
php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/home/ubuntu/

#run composer update
php /home/ubuntu/composer.phar install -d=/home/ubuntu/cme-commander

cat <<EOF > /etc/monit/conf.d/cme
check process SendEmails-Inst1
    with pidfile "$CMEDIR/monit/SendEmails/inst1.pid"
    group CME
    start program = "/usr/bin/php $CMEDIR/cme SendEmails -iinst1"
    stop program = "/bin/bash -c '/bin/kill \`/bin/cat $CMEDIR/monit/SendEmails/inst1.pid\`'"
    if mem > 1700 MB for 3 cycles then alert
    if mem > 1700 MB for 5 cycles then restart
EOF

#update monitrc file
cat <<EOF >> /etc/monit/monitrc

set httpd port 2812
use address localhost
allow localhost
EOF

service monit restart

