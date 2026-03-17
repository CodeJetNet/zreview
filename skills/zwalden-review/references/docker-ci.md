# Docker & CI/CD Reference — zwalden's Standards

## Canonical Dockerfile Pattern

zwalden has a specific Dockerfile structure he expects all services to follow.
Reference: `https://github.com/alldigitalrewards/transaction-email/blob/master/Dockerfile`

### Required Structure: Three Targets

```dockerfile
FROM php:8.2-fpm-alpine3.18 AS base

ENV LANG=en_US.UTF-8
ENV LANGUAGE=en_US:en
ENV LC_ALL=en_US.UTF-8

RUN apk add --no-cache git curl bash supervisor nginx autoconf g++ make linux-headers \
    icu-dev gettext gettext-dev \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-configure gettext

RUN docker-php-ext-install pdo pdo_mysql bcmath sockets gettext

RUN mkdir -p /run/nginx
RUN mkdir -p /run/php

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/php.ini

WORKDIR /app
EXPOSE 80

FROM base AS development
# Install XDEBUG
RUN git clone https://github.com/xdebug/xdebug.git \
    && cd xdebug \
    && phpize \
    && ./configure --enable-xdebug \
    && make \
    && make install \
    && rm -rf /app/xdebug
CMD ["/usr/local/bin/startup.sh"]

FROM base AS production
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH=${COMPOSER_AUTH}
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY . /app
RUN php /usr/local/bin/composer install --optimize-autoloader --no-dev
RUN chown -R www-data: /app
CMD ["/usr/local/bin/startup.sh"]
```

### Rules

- ✅ `COPY docker/nginx.conf` and `COPY docker/php.ini` go in the **`base`** target
- ✅ Composer installed only in **`production`** target
- ✅ Development target installs xdebug, production does not
- ✅ No unnecessary comments
- ✅ Multi-line `RUN` commands for related dependencies, not one-per-line sprawl
- ❌ Never install Composer in `base` or `development`
- ❌ Never have duplicate `COPY` statements for config files

---

## GitHub Actions / CI

### Build and Push Workflow

Required fields that zwalden checks:
```yaml
# deployment step must specify:
namespace: mpadmin  # (or appropriate namespace)
deployment: <service-name>  # e.g., "dashboard"
```

### Combining Build Steps

> "merge these two build steps"

If you have sequential Docker build steps that could be one step, combine them. Don't separate `docker build` from `docker tag` unnecessarily.

### Test Workflow

For PHP services, the test workflow must declare required extensions. zwalden will ask "Is this change required?" when extensions are added to test CI.

Standard extensions for most services:
- `pdo`, `pdo_mysql`, `bcmath`, `sockets`

Only add `redis` or `intl` if tests actually need them.

---

## docker-compose.yml

- No unnecessary blank lines
- Don't change service configuration unless there's a clear reason

```yaml
# ❌ BAD — unnecessary blank line
services:
  app:

    build: .

# ✅ GOOD
services:
  app:
    build: .
```

---

## Summary Checklist for Dockerfile PRs

- [ ] Three targets: `base`, `development`, `production`
- [ ] `nginx.conf` and `php.ini` COPY in `base`
- [ ] Composer install only in `production`
- [ ] xdebug only in `development`
- [ ] No multi-stage duplication of config files
- [ ] CI deployment step has `namespace` and `deployment` values
- [ ] No unnecessary blank lines in docker-compose.yml
