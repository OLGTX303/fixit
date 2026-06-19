# ADR-0001: Separate frontend and backend deployments

## Status

Accepted — 2026-06-19

## Context

PR3 requires Vue SPA + PHP API + MySQL. Docker was removed per project preference.

## Decision

- `fixit-frontend/` — static Vue build, env `VITE_API_URL`
- `fixit-backend/` — PHP Slim API, env `DB_*`, `JWT_SECRET`, `CORS_ORIGIN`
- Deploy independently; CORS links them

## Consequences

- Two deployment pipelines and two READMEs
- Frontend must be rebuilt when API URL changes
- Backend CORS must list exact frontend origin(s)