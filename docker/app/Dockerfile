FROM php:8.4-cli AS base

ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    ca-certificates \
    libzip-dev \
    && docker-php-ext-install zip pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

WORKDIR /app

# --- devcontainer ---
FROM base AS devcontainer

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    zsh \
    nano \
    openssh-client \
    make \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN useradd -ms /bin/zsh -u ${WWWUSER} developer

# Antigen
RUN mkdir -p /home/developer/.antigen \
    && curl -L git.io/antigen > /home/developer/.antigen/antigen.zsh \
    && chown -R developer:developer /home/developer/.antigen

COPY .zshrc /home/developer/.zshrc
RUN chown developer:developer /home/developer/.zshrc

USER developer

CMD ["tail", "-f", "/dev/null"]
