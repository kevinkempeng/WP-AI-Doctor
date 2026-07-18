#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_name="$(basename "$project_dir")"
dist_dir="$project_dir/dist"
output="$dist_dir/${project_name}-1.1.1.zip"

mkdir -p "$dist_dir"
rm -f "$output"

cd "$(dirname "$project_dir")"
zip -q -r "$output" "$project_name" \
	-x "$project_name/.git/*" \
		"$project_name/.github/*" \
		"$project_name/.wordpress-org/*" \
		"$project_name/.gitignore" \
		"$project_name/.distignore" \
		"$project_name/assets-src/*" \
		"$project_name/CONTRIBUTING.md" \
		"$project_name/SECURITY.md" \
		"$project_name/build.sh" \
		"$project_name/composer.json" \
		"$project_name/composer.lock" \
		"$project_name/docs/*" \
		"$project_name/phpcs.xml.dist" \
		"$project_name/phpunit.xml.dist" \
		"$project_name/README.md" \
		"$project_name/tests/*" \
		"$project_name/vendor/*" \
		"$project_name/dist/*"

printf '%s\n' "$output"
