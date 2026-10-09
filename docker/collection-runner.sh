#!/bin/sh
set -eu
cd /var/www/epay
case "${1:-collection}" in
    collection|subscription) worker="${1:-collection}";;
    *) echo 'Unknown worker' >&2; exit 1;;
esac
while ! php "scripts/$worker-worker.php" --ready 2>/dev/null | grep -qx READY; do sleep 30; done
exec php "scripts/$worker-worker.php"
