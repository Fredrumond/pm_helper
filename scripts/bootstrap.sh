#!/bin/bash
# Script de bootstrap do PM Helper
# Execute este script UMA VEZ após o primeiro `docker-compose up -d`
# Usage: bash scripts/bootstrap.sh

set -e

DOCKER="DOCKER_HOST=unix://$HOME/.docker/run/docker.sock docker-compose"

echo "==> [1/6] Criando projeto Laravel 13..."
$DOCKER run --rm -v $(pwd):/app -w /app \
  composer:2.7 create-project laravel/laravel:^13.0 /tmp/laravel_install --prefer-dist --no-interaction

echo "==> [2/6] Movendo arquivos do Laravel para o workspace..."
$DOCKER run --rm -v $(pwd):/app -v /tmp/laravel_install:/src \
  alpine sh -c "cp -rn /src/. /app/ 2>/dev/null || true"

echo "==> [3/6] Instalando pacotes adicionais (Breeze, Livewire)..."
$DOCKER run --rm app composer require laravel/breeze livewire/livewire --no-interaction
$DOCKER run --rm app php artisan breeze:install blade --no-interaction
$DOCKER run --rm app php artisan livewire:publish --config

echo "==> [4/6] Configurando .env..."
cp .env.example .env
sed -i '' "s/DB_HOST=127.0.0.1/DB_HOST=db/" .env
sed -i '' "s/DB_DATABASE=laravel/DB_DATABASE=pm_helper/" .env
sed -i '' "s/DB_USERNAME=root/DB_USERNAME=pm_helper/" .env
sed -i '' "s/DB_PASSWORD=/DB_PASSWORD=secret/" .env
sed -i '' "s/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=database/" .env
$DOCKER run --rm app php artisan key:generate

echo "==> [5/6] Rodando migrations..."
$DOCKER run --rm app php artisan migrate --force

echo "==> [6/6] Instalando dependências Node e compilando assets..."
$DOCKER run --rm -v $(pwd):/app -w /app node:20-alpine sh -c "npm install && npm run build"

echo ""
echo "✅ Bootstrap concluído! Acesse: http://localhost:8080"
