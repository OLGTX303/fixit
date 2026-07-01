# FixIt — Final Presentation: Canva Slide Prompts

20 slides max. Each block = one slide. Paste the **Prompt** into Canva (Magic Design / text).
Fill `[ ]` placeholders. Diagrams marked **(make in draw.io / Mermaid, then image into Canva)**.

Project one-liner: *On-demand local home-services marketplace — Vue 3 SPA + Capacitor Android, PHP Slim 4 REST API, MySQL, JWT auth, Stripe payments, end-to-end encrypted chat, provider KYC, and admin harm-review.*

---

## Slide 1 — Title
**Prompt:** Title slide for a Software Engineering final presentation. Project name "FixIt" large and bold, tagline "On-demand local home-services marketplace" below. Subtitle "PR3 Final Demonstration". Add course/module name, group name, date [ ], and a small wrench/home-services icon. Clean, professional, single accent color.

## Slide 2 — Team Profile & Roles
**Prompt:** "Team Profile & Roles" slide. A 2×2 grid of 4 member cards, each with a circular photo placeholder, name, and role title:
- **Wong Zi Qi — Frontend Lead:** Vue 3 project setup, UI design, Vue Router & Pinia config, responsive layout, Axios integration, frontend feature development & testing.
- **Bong Zi Shan — Backend & API Lead:** PHP Slim 4 setup, RESTful API development, booking & provider management logic, request validation, error handling, API testing & debugging.
- **Zuo Boyu — Database & Security Lead:** ER diagram design, MySQL schema, PDO data-access layer, JWT auth & RBAC, input validation, security implementation & testing.
- **Tang Yu Shan — DevOps, Mobile & Integration Lead:** Cloud deployment architecture, frontend–backend integration, Capacitor Android implementation & build config, environment management, integration testing, deployment & demo prep.

Modern card layout, consistent spacing, one accent color.

## Slide 3 — Task Distribution Evidence
**Prompt:** "Teamwork & Task Distribution" slide. Two-column layout. Left: a table mapping each member to their layer / branches owned / key deliverables — Wong Zi Qi (frontend: Vue SPA, components, routing), Bong Zi Shan (backend: API routes, booking/provider logic), Zuo Boyu (DB & security: schema, JWT, RBAC), Tang Yu Shan (DevOps: deployment, Android, integration). Right: a screenshot placeholder labelled "GitHub Insights — Contributors graph" and "Commit history". Footer note: "Evidence: branch-per-feature, PR reviews, conventional commits".

## Slide 4 — Problem & Users
**Prompt:** "Problem Statement & Target Users" slide. Top: problem statement — "Finding trusted, verified local home-service providers is slow, unsafe, and fragmented." Three user persona chips: Customer, Service Provider, Admin. Each chip with a one-line need. Clean icons, minimal text.

## Slide 5 — Objectives & Scope
**Prompt:** "Objectives & Functional Scope" slide. Left column "Objectives" (3–4 bullets: verified providers, secure booking & payment, private communication, trust & safety moderation). Right column "Feature Scope" as tag pills: Browse by category, Map search, Bookings, Reviews, Stripe payments, E2E chat, Provider KYC, Admin moderation. Balanced two-column design.

## Slide 6 — Use Case Diagram
**Prompt:** "Use Case Diagram" slide, title + large diagram area. **(make in draw.io)** Actors: Customer, Provider, Admin. Customer use cases: Register/Login, Search providers, Book service, Pay, Chat, Leave review. Provider: Complete KYC, Manage profile, Accept/update job, Chat. Admin: Verify providers, Manage users, Review flagged messages. Standard UML use-case notation.

## Slide 7 — Project Evolution (PR1 → PR3)
**Prompt:** "Project Evolution" slide as a horizontal 3-step timeline. PR1: Interactive UI mockup (React/JSX design canvas). PR2: Vue 3 interim build (mock JSON, no backend). PR3: Full-stack — Vue 3 + Capacitor Android, PHP Slim 4 API, MySQL, real auth/payments/encryption. One enhancement callout: "Added KYC, Stripe, and E2E encrypted chat in PR3." Arrow timeline style.

## Slide 8 — DevOps: Version Control & Collaboration
**Prompt:** "Version Control & Collaboration" slide. Left: Git branching strategy diagram — main + feature/* + fix/* branches merging via Pull Requests. Right bullets: conventional commits (feat/fix/docs), PR review before merge, conflict resolution via rebase. Add a small screenshot placeholder "GitHub commit graph". Developer-tools aesthetic.

## Slide 9 — DevOps: CI/CD & Deployment
**Prompt:** "CI/CD & Deployment" slide as a pipeline flow triggered by "git push to master": GitHub Actions (`deploy.yml`) fans out to two parallel jobs — (1) SSH into the production server (appleboy/ssh-action) and run `redeploy.sh`, which pulls the repo, rebuilds the Vue frontend, runs `composer install` + DB migrations, and reloads PHP-FPM/nginx at fixit.olgtx.com; (2) build the Android APK (`npm run build` → `cap sync` → `gradlew assembleRelease`, signed with a keystore from GitHub Secrets) and publish it as a GitHub Release, which installed apps auto-detect via `GET /api/app/latest` (OTA update). A separate `release-apk.yml` handles tagged (`v*`) milestone releases the same way. Pipeline arrow diagram, "zero manual deploy steps" callout.

## Slide 10 — System Architecture Diagram
**Prompt:** "System Architecture" slide, full-width layered diagram. **(make in draw.io / Mermaid)** Clients layer: Vue 3 SPA (web) + Capacitor Android app. API layer: PHP Slim 4 REST API with JWT auth middleware, rate limiting, CORS, security headers. Data layer: MySQL. External: Stripe API. Crypto note: AES-256-GCM + RSA-2048 run client-side; server stores ciphertext only. Boxes-and-arrows, labelled protocols (HTTPS/REST).

## Slide 11 — Sequence Diagram: Booking + Payment
**Prompt:** "Sequence Diagram — Book & Pay" slide. **(make in draw.io / Mermaid)** UML sequence: Customer → Vue SPA → Slim API → MySQL, and API → Stripe (SetupIntent → save test card → pay). Show login/JWT validation, create booking, payment confirmation, status update. Lifelines and arrows in standard UML notation.

## Slide 12 — Sequence Diagram: E2E Encrypted Chat
**Prompt:** "Sequence Diagram — Encrypted Chat" slide. **(make in draw.io / Mermaid)** UML sequence: Provider & Customer exchange per-job AES-256-GCM key (RSA-wrapped). Sender encrypts client-side → POST ciphertext → MySQL stores ciphertext only → recipient fetches → decrypts client-side. Note PIN unlock (PBKDF2-wrapped RSA private key) for new devices. Emphasise "server never sees plaintext".

## Slide 13 — Activity Diagram: Provider KYC
**Prompt:** "Activity Diagram — Provider Onboarding & KYC" slide. **(make in draw.io)** Start → Register → Upload ID → OCR + MRZ read → Anti-spoof check → decision (pass/fail) → 8-colour face liveness → decision → Admin verification → Approved/Rejected end. Include decision diamonds and a rejection loop-back. Standard UML activity notation with start/end nodes.

## Slide 14 — Deployment Diagram
**Prompt:** "Deployment Diagram" slide. **(make in draw.io)** Nodes: Client device (Browser / Android device), Web/static host (serves Vue `dist/`), Application server (nginx + PHP-FPM running Slim API), Database server (MySQL), External node (Stripe API). Connect with «HTTPS/REST» communication paths. UML deployment node notation (3D boxes).

## Slide 15 — Database: ER Diagram
**Prompt:** "Data Model — ER Diagram" slide. **(make in draw.io / dbdiagram.io)** Main entities and relationships: users (customer/provider/admin), categories, providers, bookings, reviews, messages (ciphertext), kyc_verifications, payments, legal_acceptance, crypto_keys. Show PKs/FKs and cardinality (1:N, N:M). Clean ER notation.

## Slide 16 — Data Management & Security
**Prompt:** "Data Management & Security" slide, two columns. Left "Integrity & Validation": prepared statements (SQL-injection safe), server-side validation, role-based access (JWT + role guards). Right "Sensitive Data": passwords hashed (bcrypt), E2E AES-256-GCM message encryption, RSA-2048 key wrapping, rate limiting, CORS lockdown, security headers. Shield/lock iconography.

## Slide 17 — Demo Data
**Prompt:** "Demonstration Data" slide. Show seeded demo accounts in a table: Customer (alex@email.com), Provider (marcus@email.com), Admin (admin@fixit.com) — note "realistic seeded categories, providers, bookings & reviews, not empty screens." Small screenshot placeholder of a populated provider list / map. Reassuring "ready-to-demo" tone.

## Slide 18 — Demo Walkthrough (Customer journey)
**Prompt:** "Live Demo — Customer Journey" slide as a numbered flow with screenshot placeholders: 1) Register (slider-captcha → email OTP → accept Terms/Privacy) → 2) Browse/map search → 3) Provider profile & reviews → 4) Book service → 5) Stripe test payment (save card → pay) → 6) E2E encrypted chat with provider. Phone/laptop mockup frames around screenshots.

## Slide 19 — Demo Walkthrough (Provider + Admin)
**Prompt:** "Live Demo — Provider & Admin" slide, two columns. Provider: complete KYC (ID + liveness) → manage profile → accept & update job status → chat. Admin: verify providers → manage users → review flagged (harm-review) messages. Screenshot placeholders, split layout, clear labels.

## Slide 20 — Summary & SE Practices
**Prompt:** "Summary" slide. Recap that FixIt delivers a working full-stack system AND applies proper SE practices: requirements (use cases), design (UML), implementation (Vue/PHP/MySQL), security & crypto, testing/QA, DevOps (Git + CI/CD + deployment), project management (branch/PR workflow). End with "Thank You / Q&A" and team name. Confident closing design matching the title slide.
