#!/usr/bin/env bash
#
# Installs WordPress and the WordPress PHPUnit test library for this plugin.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
# Example: bin/install-wp-tests.sh wp_tests root '' 127.0.0.1 latest
#
# Sources: github.com/WordPress/WordPress (core) and github.com/WordPress/wordpress-develop (test library).

set -e

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION=$(git ls-remote --tags https://github.com/WordPress/WordPress.git | awk -F/ '{print $3}' | grep -E '^[0-9]+\.[0-9]+(\.[0-9]+)?$' | sort -V | tail -1)
fi
echo "WordPress version: $WP_VERSION"

install_wp() {
	if [ -f "$WP_CORE_DIR/wp-settings.php" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"
	curl -sSL "https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/$WP_VERSION" | tar xz --strip-components=1 -C "$WP_CORE_DIR"
}

install_test_suite() {
	if [ -d "$WP_TESTS_DIR/includes" ]; then
		return
	fi
	local tmp
	tmp=$(mktemp -d)
	git clone --quiet --depth 1 --filter=blob:none --no-checkout -b "$WP_VERSION" https://github.com/WordPress/wordpress-develop.git "$tmp/wpdev"
	(
		cd "$tmp/wpdev"
		git sparse-checkout init --no-cone
		git sparse-checkout set '/tests/phpunit/includes/' '/tests/phpunit/data/' '/wp-tests-config-sample.php'
		git checkout --quiet
	)
	mkdir -p "$WP_TESTS_DIR"
	cp -r "$tmp/wpdev/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
	cp -r "$tmp/wpdev/tests/phpunit/data" "$WP_TESTS_DIR/data"
	cp "$tmp/wpdev/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
	rm -rf "$tmp"

	local core_dir
	core_dir=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
	sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$core_dir/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s:__DIR__ . '/src/':'$core_dir/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	rm -f "$WP_TESTS_DIR/wp-tests-config.php.bak"
}

install_db() {
	if [ "${SKIP_DB_CREATE}" = "true" ]; then
		return 0
	fi
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]}
	local DB_SOCK_OR_PORT=${PARTS[1]}
	local EXTRA=""
	if [ -n "$DB_HOSTNAME" ]; then
		if [ "$(echo "$DB_SOCK_OR_PORT" | grep -e '^[0-9]\{1,\}$')" ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif [ -n "$DB_SOCK_OR_PORT" ]; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif [ -n "$DB_HOSTNAME" ]; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA || true
}

install_wp
install_test_suite
install_db
echo "Done. Run: composer install && vendor/bin/phpunit"
