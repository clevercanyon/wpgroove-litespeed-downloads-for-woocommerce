#!/usr/bin/env bash
##
# Clever Canyon™ {@see https://clevercanyon.com}
#
#  CCCCC  LL      EEEEEEE VV     VV EEEEEEE RRRRRR      CCCCC    AAA   NN   NN YY   YY  OOOOO  NN   NN ™
# CC      LL      EE      VV     VV EE      RR   RR    CC       AAAAA  NNN  NN YY   YY OO   OO NNN  NN
# CC      LL      EEEEE    VV   VV  EEEEE   RRRRRR     CC      AA   AA NN N NN  YYYYY  OO   OO NN N NN
# CC      LL      EE        VV VV   EE      RR  RR     CC      AAAAAAA NN  NNN   YYY   OO   OO NN  NNN
#  CCCCC  LLLLLLL EEEEEEE    VVV    EEEEEEE RR   RR     CCCCC  AA   AA NN   NN   YYY    OOOO0  NN   NN
##

##
# WP docker entrypoint.
#
# @since 1.0.0
#
# @note PLEASE DO NOT EDIT THIS FILE!
# This file and the contents of it are updated automatically.
#
# - Instead of editing this file, you can modify `./dev/.libs/docker/wp/entry~prj.bash`.
# - Instead of editing this file, please review source repository {@see https://o5p.me/LevQOD}.
##

# ---------------------------------------------------------------------------------------------------------------------
# Source a few dependencies.
# ---------------------------------------------------------------------------------------------------------------------

if [[ -f "${WP_DOCKER_HOST_PROJECT_DIR}"/.c10n-utilities ]];
	then c10n_utilities_path="${WP_DOCKER_HOST_PROJECT_DIR}";
else c10n_utilities_path="${WP_DOCKER_HOST_PROJECT_DIR}"/vendor/clevercanyon/utilities; fi;

if [[ -f "${c10n_utilities_path}"/dev/utilities/load.bash ]]; then
	. "${c10n_utilities_path}"/dev/utilities/load.bash;
	. "${c10n_utilities_path}"/dev/utilities/bash/partials/require-root.bash;
	. "${c10n_utilities_path}"/dev/utilities/bash/partials/require-wp-docker.bash;
else
	echo -e "\e[38;5;255m\e[48;5;124m\e[1mMissing required dependency: '${c10n_utilities_path}'\e[0m\e[49m\e[39m";
	echo -e "\e[38;5;255m\e[48;5;124m\e[1mHave you run 'composer install' yet?\e[0m\e[49m\e[39m";
	false; # Exit w/ error status.
fi;
# ---------------------------------------------------------------------------------------------------------------------
# Run parent container's entrypoint.
# ---------------------------------------------------------------------------------------------------------------------

/usr/local/bin/docker-entrypoint.sh apache2-noop;

# ---------------------------------------------------------------------------------------------------------------------
# Maybe run initial installation/setup.
# ---------------------------------------------------------------------------------------------------------------------
# The routines below install several things, including WordPress.
# It also installs WordPress plugins, themes, and handles activation.

if [[ ! -f /wp-docker/container/setup-complete ]]; then
	mkdir --parents /wp-docker/container;
	touch /wp-docker/container/setup-complete;

	# -----------------------------------------------------------------------------------------------------------------
	# Adjust WP-CLI configuration.
	# -----------------------------------------------------------------------------------------------------------------
	# This file has already been created by `/wp-docker/image/setup`.
	# The `path:` is in there already. We need to add `url:` and `user:` now.
	{
		echo "url : ${WP_DOCKER_WORDPRESS_URL}";
		echo "user: ${WP_DOCKER_WORDPRESS_ADMIN_USERNAME}";
	} >> "${WP_DOCKER_ROOT_HOME_DIR}"/.wp-cli/config.yml;

	cp --preserve=all "${WP_DOCKER_ROOT_HOME_DIR}"/.wp-cli/config.yml "${WP_DOCKER_WEB_SERVER_USER_HOME_DIR}"/.wp-cli/config.yml;
	chown "${WP_DOCKER_WEB_SERVER_USER}"                              "${WP_DOCKER_WEB_SERVER_USER_HOME_DIR}"/.wp-cli/config.yml;

	# -----------------------------------------------------------------------------------------------------------------
	# Install WordPress in different ways; depending on project layout.
	# -----------------------------------------------------------------------------------------------------------------

	if [[ "${WP_DOCKER_COMPOSE_PROJECT_TYPE}" == 'library' \
		&& "${WP_DOCKER_COMPOSE_PROJECT_LAYOUT}" == 'wp-network' ]]; then
		# -------------------------------------------------------------------------------------------------------------
		# Install WordPress core.
		# -------------------------------------------------------------------------------------------------------------

		wp core multisite-install --allow-root \
			--title="${WP_DOCKER_WORDPRESS_SITE_TITLE}" \
			--admin_user="${WP_DOCKER_WORDPRESS_ADMIN_USERNAME}" \
			--admin_password="${WP_DOCKER_WORDPRESS_ADMIN_PASSWORD}" \
			--admin_email="${WP_DOCKER_WORDPRESS_ADMIN_EMAIL}" --skip-email \
			--skip-config; # Assume network is already configured by project.

	elif [[ "${WP_DOCKER_COMPOSE_PROJECT_TYPE}" == 'library' \
		&& "${WP_DOCKER_COMPOSE_PROJECT_LAYOUT}" == 'wp-website' ]]; then
		# -------------------------------------------------------------------------------------------------------------
		# Install WordPress core.
		# -------------------------------------------------------------------------------------------------------------

		wp core install --allow-root \
			--title="${WP_DOCKER_WORDPRESS_SITE_TITLE}" \
			--admin_user="${WP_DOCKER_WORDPRESS_ADMIN_USERNAME}" \
			--admin_password="${WP_DOCKER_WORDPRESS_ADMIN_PASSWORD}" \
			--admin_email="${WP_DOCKER_WORDPRESS_ADMIN_EMAIL}" --skip-email;
	else
		# -------------------------------------------------------------------------------------------------------------
		# Install WordPress core.
		# -------------------------------------------------------------------------------------------------------------

		wp core install --allow-root \
			--title="${WP_DOCKER_WORDPRESS_SITE_TITLE}" \
			--admin_user="${WP_DOCKER_WORDPRESS_ADMIN_USERNAME}" \
			--admin_password="${WP_DOCKER_WORDPRESS_ADMIN_PASSWORD}" \
			--admin_email="${WP_DOCKER_WORDPRESS_ADMIN_EMAIL}" --skip-email;

		# -------------------------------------------------------------------------------------------------------------
		# Maybe update `.htaccess` file for mulitisite installs.
		# -------------------------------------------------------------------------------------------------------------

		if [[ "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" == 'subdomains' ]]; then
			wp core multisite-convert --allow-root \
				--title="${WP_DOCKER_WORDPRESS_SITE_TITLE}" --subdomains;
			{
				echo 'RewriteEngine On';
				echo 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]';
				echo 'RewriteBase /';
				echo 'RewriteRule ^index\.php$ - [L]';
				echo '';
				echo 'RewriteRule ^wp-admin$ wp-admin/ [R=301,L]';
				echo '';
				echo 'RewriteCond %{REQUEST_FILENAME} -f [OR]';
				echo 'RewriteCond %{REQUEST_FILENAME} -d';
				echo 'RewriteRule ^ - [L]';
				echo 'RewriteRule ^(wp-(content|admin|includes).*) $1 [L]';
				echo 'RewriteRule ^(.*\.php)$ $1 [L]';
				echo 'RewriteRule . index.php [L]';
			} > "${WP_DOCKER_WORDPRESS_DIR}"/.htaccess;

		elif [[ -n "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" ]]; then
			wp core multisite-convert --allow-root \
				--title="${WP_DOCKER_WORDPRESS_SITE_TITLE}";
			{
				echo 'RewriteEngine On';
				echo 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]';
				echo 'RewriteBase /';
				echo 'RewriteRule ^index\.php$ - [L]';
				echo '';
				echo 'RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]';
				echo '';
				echo 'RewriteCond %{REQUEST_FILENAME} -f [OR]';
				echo 'RewriteCond %{REQUEST_FILENAME} -d';
				echo 'RewriteRule ^ - [L]';
				echo 'RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]';
				echo 'RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]';
				echo 'RewriteRule . index.php [L]';
			} > "${WP_DOCKER_WORDPRESS_DIR}"/.htaccess;
		fi;
		# -------------------------------------------------------------------------------------------------------------
		# Maybe update to latest version.
		# -------------------------------------------------------------------------------------------------------------

		wp core update --allow-root;

		if [[ -n "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" ]]; then
			wp core update-db --network --allow-root;
		else
			wp core update-db --allow-root;
		fi;
		wp theme  update --all --allow-root;
		wp plugin update --all --allow-root;

		# -------------------------------------------------------------------------------------------------------------
		# Maybe install plugins|themes network-wide.
		# -------------------------------------------------------------------------------------------------------------

		if [[ -n "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" ]]; then
			if [[ -n "${WP_DOCKER_WORDPRESS_INSTALL_PLUGINS}" ]]; then
				while IFS=',' read -ra _plugins; do
					for _plugin in "${_plugins[@]}"; do
						wp plugin install "${_plugin}" --activate-network --allow-root;
					done;
				done <<< "${WP_DOCKER_WORDPRESS_INSTALL_PLUGINS}";
			fi;
			if [[ -n "${WP_DOCKER_WORDPRESS_INSTALL_THEME}" && -n "${WP_DOCKER_WORDPRESS_INSTALLED_THEME_SLUG}" ]]; then
				wp theme install "${WP_DOCKER_WORDPRESS_INSTALL_THEME}" --allow-root;
				wp theme enable "${WP_DOCKER_WORDPRESS_INSTALLED_THEME_SLUG}" --network --activate --allow-root;
			fi;
		# -------------------------------------------------------------------------------------------------------------
		# Maybe install plugins|themes for standard WordPress.
		# -------------------------------------------------------------------------------------------------------------

		else
			if [[ -n "${WP_DOCKER_WORDPRESS_INSTALL_PLUGINS}" ]]; then
				while IFS=',' read -ra _plugins; do
					for _plugin in "${_plugins[@]}"; do
						wp plugin install "${_plugin}" --activate --allow-root;
					done;
				done <<< "${WP_DOCKER_WORDPRESS_INSTALL_PLUGINS}";
			fi;
			if [[ -n "${WP_DOCKER_WORDPRESS_INSTALL_THEME}" ]]; then
				wp theme install "${WP_DOCKER_WORDPRESS_INSTALL_THEME}" --activate --allow-root;
			fi;
		fi;
		# -------------------------------------------------------------------------------------------------------------
		# Maybe link a project's WordPress plugin|theme directory and activate.
		# -------------------------------------------------------------------------------------------------------------

		if [[ "${WP_DOCKER_COMPOSE_PROJECT_TYPE}" == 'library' \
				&& "${WP_DOCKER_COMPOSE_PROJECT_LAYOUT}" == 'wp-plugin' \
				&& -f "${WP_DOCKER_HOST_PROJECT_DIR}"/trunk/plugin.php ]];
		then
			ln -s "${WP_DOCKER_HOST_PROJECT_DIR}"/trunk "${WP_DOCKER_WORDPRESS_DIR}"/wp-content/plugins/"${WP_DOCKER_COMPOSE_PROJECT_SLUG}";

			if [[ -n "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" ]]; then
				wp plugin activate "${WP_DOCKER_COMPOSE_PROJECT_SLUG}" --network --allow-root;
			else
				wp plugin activate "${WP_DOCKER_COMPOSE_PROJECT_SLUG}" --allow-root;
			fi;
		elif [[ "${WP_DOCKER_COMPOSE_PROJECT_TYPE}" == 'library' \
				&& "${WP_DOCKER_COMPOSE_PROJECT_LAYOUT}" == 'wp-theme' \
				&& -f "${WP_DOCKER_HOST_PROJECT_DIR}"/trunk/theme.php ]];
		then
			ln -s "${WP_DOCKER_HOST_PROJECT_DIR}"/trunk "${WP_DOCKER_WORDPRESS_DIR}"/wp-content/themes/"${WP_DOCKER_COMPOSE_PROJECT_SLUG}";

			if [[ -n "${WP_DOCKER_WORDPRESS_MULTISITE_TYPE}" ]]; then
				wp theme enable "${WP_DOCKER_COMPOSE_PROJECT_SLUG}" --network --activate --allow-root;
			else
				wp theme enable "${WP_DOCKER_COMPOSE_PROJECT_SLUG}" --activate --allow-root;
			fi;
		fi;
		# -------------------------------------------------------------------------------------------------------------
		# Install `info.php` file for debugging.
		# -------------------------------------------------------------------------------------------------------------

		echo '<?php phpinfo();' > "${WP_DOCKER_WORDPRESS_DIR}"/info.php;

		# -------------------------------------------------------------------------------------------------------------
		# Update WordPress directory permissions.
		# -------------------------------------------------------------------------------------------------------------

		chown --recursive "${WP_DOCKER_WEB_SERVER_USER}" "${WP_DOCKER_WORDPRESS_DIR}";
		find "${WP_DOCKER_WORDPRESS_DIR}" -type d -exec chmod 0755 {} \;; # Includes the directory itself, too.
		find "${WP_DOCKER_WORDPRESS_DIR}" -type f -exec chmod 0644 {} \;; # All files, in this case.
	fi;
fi;
# ---------------------------------------------------------------------------------------------------------------------
# Maybe run project-specific entrypoint hook.
# ---------------------------------------------------------------------------------------------------------------------

if [[ -x "${WP_DOCKER_HOST_PROJECT_DIR}"/dev/.libs/docker/wp/entry~prj.bash ]]; then
	. "${WP_DOCKER_HOST_PROJECT_DIR}"/dev/.libs/docker/wp/entry~prj.bash;
fi;
# ---------------------------------------------------------------------------------------------------------------------
# Start Apache.
# ---------------------------------------------------------------------------------------------------------------------

apache2-foreground;
