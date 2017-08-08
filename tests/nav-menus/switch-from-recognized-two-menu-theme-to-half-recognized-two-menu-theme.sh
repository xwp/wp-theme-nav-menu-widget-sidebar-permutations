#!/bin/bash

cd "$( dirname "$0" )"
set -e

echo "Backing up current DB:"
backup_file=$( mktemp ).sql
wp db export "$backup_file"

wp db reset --yes

url="http://src.wordpress-develop.dev/"

wp core install --url="$url" --title="WordPress Develop" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
wp plugin activate customizer-browser-history
wp cache flush
wp option set fresh_site 0

wp theme activate nav-menu-widget-sidebar-permutations/nav-menus-top-footer
first_menu_id=$(wp menu create "First" --porcelain)
wp menu item add-custom $first_menu_id "Top Item" "http://example.com/top-item"
wp menu location assign $first_menu_id "top"

second_menu_id=$(wp menu create "Second" --porcelain)
wp menu item add-custom $second_menu_id "Footer Item" "http://example.com/footer-item"
wp menu location assign $second_menu_id "footer"

before_switch_output=$( mktemp ).before-switch.html
wget -O "$before_switch_output" "$url"

wp theme activate nav-menu-widget-sidebar-permutations/nav-menus-aaa-footer

after_switch_output=$( mktemp ).after-switch.html
wget -O "$after_switch_output" "$url"

echo "Switch back to the original theme and ensure the original nav menus are intact"
wp theme activate nav-menu-widget-sidebar-permutations/nav-menus-top-footer

after_switch_back_output=$( mktemp ).after-switch-back.html
wget -O "$after_switch_back_output" "$url"

echo "Restoring backup"
wp db import "$backup_file"
wp cache flush

echo "Running assertions:"
set -x
if ! grep -q '"menu-first-container"><ul id="top-menu"' "$before_switch_output"; then
	echo "🚫  Failed: before theme switch, first menu failed to get assigned to top location"
	exit 1
fi
if ! grep -q '"menu-second-container"><ul id="footer-menu"' "$before_switch_output"; then
	echo "🚫  Failed: before theme switch, second menu failed to get assigned to footer location"
	exit 1
fi

if ! grep -q 'Nav menu aaa unassigned.' "$after_switch_output"; then
	echo "🚫  Failed: after theme switch, expected aaa menu location to not be assigned"
	exit 1
fi
if ! grep -q '"menu-second-container"><ul id="footer-menu"' "$before_switch_output"; then
	echo "🚫  Failed: before theme switch, second menu failed to get assigned to footer location"
	exit 1
fi

if ! grep -q '"menu-first-container"><ul id="top-menu"' "$after_switch_back_output"; then
	echo "🚫  Failed: after location re-assignment and theme switch back, first menu failed to get assigned to top location"
	exit 1
fi
if ! grep -q '"menu-second-container"><ul id="footer-menu"' "$after_switch_back_output"; then
	echo "🚫  Failed: after location re-assignment and theme switch back, second menu failed to get assigned to footer location"
	exit 1
fi

set +x

echo "✅  Tests pass"
