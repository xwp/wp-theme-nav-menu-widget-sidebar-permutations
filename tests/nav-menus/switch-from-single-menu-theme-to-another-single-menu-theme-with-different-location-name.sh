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
first_menu_id=$(wp menu create "First" --porcelain)
wp menu item add-custom $first_menu_id "Item" "http://example.com/primary-item"
wp menu location assign $first_menu_id "foo"

second_menu_id=$(wp menu create "Unused" --porcelain)
wp menu item add-custom $second_menu_id "Unused item" "http://example.com/secondary-item"

before_switch_output=$( mktemp ).before-switch.html
wget -O "$before_switch_output" "$url"

wp theme activate nav-menu-widget-sidebar-permutations/nav-menu-bar

after_switch_output=$( mktemp ).after-switch.html
wget -O "$after_switch_output" "$url"

echo "Restoring backup"
wp db import "$backup_file"
wp cache flush

echo "Running assertions:"
if ! grep -q 'class="menu-first-container"><ul id="foo-menu"' "$before_switch_output"; then
	echo "🚫  Failed: first menu was not assigned to the var location"
	exit 1
fi
if ! grep -q 'class="menu-first-container"><ul id="bar-menu"' "$after_switch_output"; then
	echo "🚫  Failed: first menu was not assigned to the bar location"
	exit 1
fi

echo "✅  Tests pass"
