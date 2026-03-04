FROM node:24-slim AS node-builder
# Install acorn (JS AST parser) globally so we can copy just the package
RUN npm install -g acorn@8

FROM php:8.4-cli

# Copy Node.js runtime for the test262 transpiler
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
# Copy acorn package (used by tools/transpile-test262.mjs)
COPY --from=node-builder /usr/local/lib/node_modules/acorn /usr/local/lib/node_modules/acorn

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
