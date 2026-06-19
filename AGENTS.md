# FixIt — Agent instructions

## Agent skills

### Issue tracker

Issues and PRDs live as local markdown under `.scratch/`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default mattpocock/skills vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Multi-context monorepo: `CONTEXT-MAP.md` at the repo root points to `fixit-frontend/CONTEXT.md` and `fixit-backend/CONTEXT.md`. See `docs/agents/domain.md`.

## Project layout

- `fixit-frontend/` — Vue 3 SPA (deploy separately as static site)
- `fixit-backend/` — PHP Slim 4 API (deploy separately; requires MySQL)

No Docker. Each app has its own README and environment configuration.

## Security

Before shipping changes, read `SECURITY.md` and run through the production checklist.