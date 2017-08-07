#!/bin/bash

cd "$( dirname "$0" )"
set -e

echo "Backing up current DB:"
backup_file=$( mktemp ).sql
wp db export "$backup_file"

wp db reset --yes

url="http://src.wordpress-develop.dev/"

wp core install --url="$url" --title="WordPress Develop" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
wp cache flush
wp option set fresh_site 0

wp theme activate nav-menu-widget-sidebar-permutations/nav-menu-foo
foo_menu_id=$(wp menu create "Foo" --porcelain)
wp menu item add-custom $foo_menu_id "Foo item" "http://example.com/primary-item"
wp menu location assign $foo_menu_id "foo"

unassigned_menu_id=$(wp menu create "Unused" --porcelain)
wp menu item add-custom $unassigned_menu_id "Unused item" "http://example.com/secondary-item"

wp theme activate nav-menu-widget-sidebar-permutations/nav-menu-bar

output_file=$( mktemp ).html
wget -O "$output_file" "$url"

echo "Restoring backup"
wp db import "$backup_file"
wp cache flush

echo "Running assertions:"
if ! grep -q 'class="menu-foo-container"><ul id="bar-menu"' "$output_file"; then
	echo "Failed: top menu failed to get assigned to primary location"
	exit 1
fi

echo "Tests pass"
