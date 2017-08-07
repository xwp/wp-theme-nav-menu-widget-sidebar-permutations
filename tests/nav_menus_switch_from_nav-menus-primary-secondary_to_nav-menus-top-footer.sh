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

wp theme activate nav-menu-widget-sidebar-permutations/nav-menus-primary-secondary
primary_menu_id=$(wp menu create "Primary" --porcelain)
wp menu item add-custom $primary_menu_id "Primary item" "http://example.com/primary-item"
wp menu location assign $primary_menu_id "primary"

secondary_menu_id=$(wp menu create "Secondary" --porcelain)
wp menu item add-custom $secondary_menu_id "Secondary item" "http://example.com/secondary-item"
wp menu location assign $secondary_menu_id "secondary"

wp theme activate nav-menu-widget-sidebar-permutations/nav-menus-top-footer

output_file=$( mktemp ).html
wget -O "$output_file" "$url"

echo "Restoring backup"
wp db import "$backup_file"
wp cache flush

echo "Running assertions:"
if ! grep -q '"menu-primary-container"><ul id="top-menu"' "$output_file"; then
	echo "Failed: top menu failed to get assigned to primary location"
	exit 1
fi
if ! grep -q '"menu-secondary-container"><ul id="footer-menu"' "$output_file"; then
	echo "Failed: footer menu failed to get assigned to secondary location"
	exit 1
fi

echo "Tests pass"
