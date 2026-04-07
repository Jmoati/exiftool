RUN := docker compose run --rm php

default: help

help:
	@echo "\033[33mUsage:\033[39m"
	@echo "  make COMMAND"
	@echo ""
	@echo "\033[33mAvailable commands:\033[39m"
	@echo "\033[32m   check                  \033[39m   run some checks/test to ensure your work will not break the CI build"
	@echo "\033[32m   phpstan                \033[39m   run phpstan checks"
	@echo "\033[32m   phpunit                \033[39m   run phpunit tests"
	@echo "\033[32m   cs                     \033[39m   show files that need to be fixed"
	@echo "\033[32m   composer               \033[39m   install backend vendors"
	@echo "\033[32m   cs-fixer               \033[39m   fix files that need to be fixed"
	@echo "\033[32m   help                   \033[39m   display this help"
	@echo "\033[32m   install                \033[39m   install the project or when you switch to another git branch"

check: cs-fixer phpstan phpunit

phpunit: vendor
	$(RUN) vendor/bin/phpunit

phpstan: vendor
	$(RUN) vendor/bin/phpstan analyse -c phpstan.neon --level=max src/

cs: vendor
	$(RUN) vendor/bin/php-cs-fixer fix --dry-run --diff --verbose

composer:
	$(RUN) composer install --prefer-dist --no-progress --no-suggest

cs-fixer: vendor
	$(RUN) php vendor/bin/php-cs-fixer fix --verbose

install: composer

# only run if vendor directory does not exist
vendor:
	$(MAKE) composer
