#!/bin/sh
set -eu
supervisorctl -c /etc/supervisord.conf status php-fpm | grep -q RUNNING
supervisorctl -c /etc/supervisord.conf status scheduler | grep -q RUNNING
php -r '$socket = @fsockopen("127.0.0.1", 9000, $code, $message, 2); if (!$socket) exit(1); fclose($socket);'
