#!/bin/sh
set -eu
cd /var/www/epay
while ! php scripts/collection-worker.php --ready 2>/dev/null | grep -qx READY; do sleep 30; done
exec php scripts/collection-worker.php
