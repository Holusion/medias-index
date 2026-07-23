#!/bin/sh
set -e

# The document root's .htaccess must be the very file that gets installed on the
# host, so that local development exercises the real rewrite and access rules.
#
# It is copied at startup rather than:
#   - bind-mounted as a single file, which goes stale the moment an editor
#     replaces the file's inode, and then silently serves the old rules;
#   - symlinked into the app mount, which Apache cannot read through here
#     (EACCES on the symlink even though the target is readable).
#
# Consequence: after editing deploy/www.htaccess, run `docker compose restart web`.
if [ -f /var/www/html/app/deploy/www.htaccess ]; then
    cp /var/www/html/app/deploy/www.htaccess /var/www/html/.htaccess
fi

exec docker-php-entrypoint "$@"
