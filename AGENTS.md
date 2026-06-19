# FixIt — Agent instructions

## Project layout

- `fixit-frontend/` — Vue 3 SPA (deploy separately as static site)
- `fixit-backend/` — PHP Slim 4 API (deploy separately; requires MySQL)
- `fixit-pr2/` — PR2 milestone (mock JSON Vue app, reference)
- `fixit/` — PR1 milestone (UI mockup, reference)

No Docker. Each app has its own README and environment configuration.

## Context map

See `CONTEXT-MAP.md` for `fixit-frontend/CONTEXT.md` and `fixit-backend/CONTEXT.md`.

## Security

Before shipping changes, read `SECURITY.md` and run through the production checklist.