# Private Fitness – local dev

## Požadavky
- Docker Desktop
- Git

## Start
1) Zkopíruj `.env.example` na `.env` a doplň hesla.
2) `docker compose up -d`
3) Otevři `http://localhost:8080`

## Co je ve verzi
- `docker-compose.yml`, `docker/php.ini`
- `wp-content/themes/pf-child` (child theme)

## Co se necommitne
- WordPress core, pluginy, uploads, cache, tajná `.env`.
