# FixIt — PlantUML Diagrams

Paste each block into [plantuml.com/plantuml](https://www.plantuml.com/plantuml) or VS Code PlantUML, export PNG/SVG, drop into Canva.

---

## Slide 6 — Use Case Diagram

```plantuml
@startuml UseCase
left to right direction
skinparam packageStyle rectangle
actor Customer
actor Provider
actor Admin

rectangle FixIt {
  usecase "Register / Login" as UC_Auth
  usecase "Search Providers\n(category + map)" as UC_Search
  usecase "View Provider Profile" as UC_View
  usecase "Book Service" as UC_Book
  usecase "Pay (Stripe)" as UC_Pay
  usecase "Encrypted Chat" as UC_Chat
  usecase "Chat Notifications" as UC_Notif
  usecase "View Order Details\n& History Timeline" as UC_Order
  usecase "Leave Review" as UC_Review
  usecase "Complete KYC\n(ID + Liveness)" as UC_KYC
  usecase "Manage Profile" as UC_Profile
  usecase "Update Job Status" as UC_Job
  usecase "Verify Providers" as UC_Verify
  usecase "Manage Users" as UC_Users
  usecase "Review Flagged Messages" as UC_Harm
}

Customer --> UC_Auth
Customer --> UC_Search
Customer --> UC_View
Customer --> UC_Book
Customer --> UC_Pay
Customer --> UC_Chat
Customer --> UC_Notif
Customer --> UC_Order
Customer --> UC_Review

Provider --> UC_Auth
Provider --> UC_KYC
Provider --> UC_Profile
Provider --> UC_Job
Provider --> UC_Chat
Provider --> UC_Notif
Provider --> UC_Order

Admin --> UC_Verify
Admin --> UC_Users
Admin --> UC_Harm
Admin --> UC_Order

UC_Book ..> UC_Pay : <<include>>
UC_Book ..> UC_Auth : <<include>>
UC_Chat ..> UC_Notif : <<include>>
@enduml
```

---

## Slide 10 — System Architecture Diagram

```plantuml
@startuml Architecture
skinparam componentStyle rectangle
skinparam shadowing false

package "Clients" {
  [Vue 3 SPA\n(Vite)] as Web
  [Capacitor\nAndroid App] as Android
}

package "API Server (PHP Slim 4)" {
  [Rate Limit /\nCORS / Headers] as Sec
  [JWT Auth + RBAC\nMiddleware] as JWT
  [SecureChannel\nMiddleware (X25519)] as SCM
  [Controllers\n(Auth, Booking, Provider,\nPayment, Crypto, Admin, KYC)] as API
  [Services\n(SliderCaptcha, Mail, Stripe,\nFaceMatch)] as Svc
  [Models\n(PDO data-access)] as PDO
}

database "MySQL" as DB
cloud "Stripe API\n(test mode)" as Stripe
cloud "SMTP / Mail" as Mail
cloud "Google Maps\nJS API" as Maps
node "Face-Match Gateway\n(LAN)" as FaceMatch

note bottom of Web
  Chat E2E: AES-256-GCM + RSA-2048 client-side.
  Per-interaction channel: X25519 handshake +
  HKDF + AES-256-GCM + HMAC on payments,
  chat and order-detail requests/responses.
  Chat alerts: client polls + Web/Local
  Notifications (no FCM / no push server).
end note

Web --> Sec : HTTPS / REST (JSON)
Android --> Sec : HTTPS / REST (JSON)
Sec --> JWT
JWT --> SCM : sensitive routes
SCM --> API
JWT --> API : plain routes
API --> Svc
API --> PDO
PDO --> DB
Svc --> Stripe : payments
Svc --> Mail : OTP email
Svc --> FaceMatch : KYC selfie vs ID photo
API --> Web : maps_api_key\n(GET /api/config/maps)
Web --> Maps : load JS SDK directly\n(key never bundled in source)
@enduml
```

---

## Slide 11 — Sequence Diagram: Booking + Payment

```plantuml
@startuml SeqBookingPayment
actor Customer
participant "Vue SPA" as SPA
participant "Slim API" as API
database "MySQL" as DB
participant "Stripe" as Stripe

Customer -> SPA : Select provider & book
SPA -> API : POST /bookings (Bearer JWT)
API -> API : Validate JWT + role
API -> DB : INSERT Job (status=requested)
DB --> API : job id
API --> SPA : 201 booking created

== Save a test card (one-time) ==
SPA -> API : POST /payments/stripe/setup-intent
API -> Stripe : Create SetupIntent
Stripe --> API : client_secret
API --> SPA : client_secret
SPA -> Stripe : Confirm card (test mode)
Stripe --> SPA : payment_method_id
SPA -> API : POST /payments/stripe/save-payment-method
API -> Stripe : Attach payment method
API -> DB : store stripe_* on User

== Pay ==
SPA -> API : POST /payments/stripe/pay-with-saved-method
API -> Stripe : Create & confirm PaymentIntent
Stripe --> API : payment succeeded
API -> DB : INSERT StripePayment + update Job
API --> SPA : 200 confirmed
SPA --> Customer : Booking confirmed
@enduml
```

---

## Slide 12 — Sequence Diagram: E2E Encrypted Chat

```plantuml
@startuml SeqEncryptedChat
actor Customer
participant "Customer App" as CApp
participant "Slim API" as API
database "MySQL" as DB
participant "Provider App" as PApp
actor Provider

== One-time PIN / keypair setup ==
CApp -> API : GET /crypto/status
CApp -> CApp : Derive PIN key (PBKDF2),\ngenerate RSA-2048 keypair
CApp -> API : POST /crypto/pin/setup\n(public_key_jwk, wrapped_private_key)
API -> DB : store UserCrypto

== Key exchange (per job) ==
CApp -> API : GET /users/{id}/crypto/public-key
API -> DB : fetch public_key_jwk
API --> CApp : RSA public key
CApp -> CApp : Generate AES-256 job key,\nwrap with RSA
CApp -> API : PUT /jobs/{id}/crypto/key (encrypted_job_key)
API -> DB : store JobCryptoKey (per user)

== Send message ==
Customer -> CApp : Type message
CApp -> CApp : Harm screening, then\nAES-256-GCM encrypt
CApp -> API : POST /jobs/{id}/messages\n(ciphertext, iv, content_hash)
API -> DB : store Message (ciphertext only)
PApp -> API : GET /jobs/{id}/messages
API -> DB : read ciphertext
API --> PApp : ciphertext
PApp -> PApp : Unlock private key (PIN/PBKDF2),\ndecrypt job key, decrypt message
PApp --> Provider : Plaintext message

note over CApp, API
  Two layers: (1) app-layer E2E ciphertext (above) and
  (2) the per-interaction encrypted channel that wraps every
  /jobs/{id}/messages request + response (same as payments).
  GET sends its encrypted payload in the X-Sec-Body header.
end note
note over API, DB : Server never sees plaintext
@enduml
```

---

## Slide 13 — Activity Diagram: Provider KYC

```plantuml
@startuml ActivityKYC
start
:Provider registers;
:Upload ID document;
:OCR + MRZ read;
:Anti-spoof check;
if (ID valid?) then (no)
  :Reject / re-upload;
  stop
else (yes)
endif
:8-colour face liveness;
if (Liveness pass?) then (no)
  :Reject / retry;
  stop
else (yes)
endif
:Queue for admin review;
:Admin verifies;
if (Approved?) then (yes)
  :Mark provider verified;
  :Provider goes live;
  stop
else (no)
  :Reject with reason;
  stop
endif
@enduml
```

---

## Slide 14 — Deployment Diagram

> Includes the real CI/CD path: `.github/workflows/deploy.yml` runs on every push to `master` —
> one job SSHes into the server and redeploys, a second job builds and publishes a signed Android
> APK. `release-apk.yml` handles tagged (`v*`) milestone releases the same way.

```plantuml
@startuml Deployment
actor Developer

node "GitHub" {
  artifact "master branch" as Master
  component "GitHub Actions\n(deploy.yml)" as Actions
  artifact "GitHub Release\n(signed APK)" as Release
}

node "Client Device" {
  artifact "Browser\n(Vue dist/)" as Browser
  artifact "Android App\n(Capacitor APK)" as APK
}

node "Static Host\n(nginx)" {
  artifact "Vue SPA build\n(dist/)" as Dist
}

node "Application Server\n(aaPanel: nginx + PHP-FPM)" {
  artifact "Slim 4 API" as Slim
  artifact "redeploy.sh" as Redeploy
}

node "Database Server" {
  database "MySQL 8" as DB
}

cloud "Stripe API" as Stripe

Developer --> Master : git push
Master --> Actions : triggers on push

Actions --> Redeploy : SSH (appleboy/ssh-action)\n« sudo redeploy.sh »
Redeploy --> Dist : pull + npm build
Redeploy --> Slim : pull + composer install\n+ run DB migrations
Redeploy --> DB : apply migrations

Actions --> Actions : npm build + npx cap sync\n+ gradlew assembleRelease
Actions --> Release : publish signed APK\n(softprops/action-gh-release)

Browser --> Dist : HTTPS
Browser --> Slim : HTTPS / REST
APK --> Slim : HTTPS / REST
APK --> Slim : GET /api/app/latest\n(OTA update check)
Slim --> Release : GitHub API\n(latest release, cached 5 min)
Slim --> DB : PDO / TCP 3306
Slim --> Stripe : HTTPS
@enduml
```

---

## Slide 15 — ER Diagram

> Exact to `schema.sql` + migrations (latest). Representative columns shown, not every column
> (e.g. `User` also holds Stripe-customer + legal-acceptance fields; `ProviderProfile` holds the
> full KYC column set). Auxiliary tables `EmailOtp`, `enc_session`, `enc_nonce` omitted for clarity.

```plantuml
@startuml ERD
hide circle
skinparam linetype ortho

entity User {
  * id : INT <<PK>>
  --
  name
  email <<unique>>
  password_hash
  role : customer|provider|admin
  phone, region, latitude, longitude
  avatar_url
  stripe_test_customer_id
  terms_accepted_at / privacy_accepted_at
}

entity ServiceCategory {
  * id : INT <<PK>>
  --
  name
  description
  icon_url
}

entity ProviderProfile {
  * id : INT <<PK>>
  --
  user_id : INT <<FK,unique>>
  bio, location, base_rate
  is_verified
  kyc_status (enum)
  kyc_id_type / kyc_id_confidence
  kyc_liveness_passed / kyc_liveness_score
  avg_rating, latitude, longitude
}

entity ProviderCategory {
  * id : INT <<PK>>
  --
  provider_id : INT <<FK>>
  category_id : INT <<FK>>
}

entity ProviderService {
  * id : INT <<PK>>
  --
  provider_id : INT <<FK>>
  name, price, description, image_url
  is_active, sort_order
}

entity Job {
  * id : INT <<PK>>
  --
  customer_id : INT <<FK>>
  provider_id : INT <<FK>>
  category_id : INT <<FK>>
  status (enum)
  scheduled_at, address
  total, coupon_id <<FK>>, discount_amount
  .. order-history timestamps ..
  created_at, accepted_at
  in_progress_at, completed_at, cancelled_at
}

entity Review {
  * id : INT <<PK>>
  --
  job_id : INT <<FK>>
  rating (1..5)
  comment, created_at
}

entity Message {
  * id : INT <<PK>>
  --
  job_id : INT <<FK>>
  sender_id : INT <<FK>>
  body, ciphertext, iv, is_encrypted
  harm_status (enum), harm_categories
  content_hash, sent_at
}

entity UserCrypto {
  * user_id : INT <<PK,FK>>
  --
  pin_salt, pin_verifier
  public_key_jwk
  wrapped_private_key, private_key_iv
}

entity JobCryptoKey {
  * id : INT <<PK>>
  --
  job_id : INT <<FK>>
  user_id : INT <<FK>>
  encrypted_job_key
}

entity HarmMessageReview {
  * id : INT <<PK>>
  --
  message_id : INT <<FK>>
  job_id, sender_id
  harm_status (enum), harm_categories
  reviewed_by : INT <<FK>>, admin_notes
}

entity StripePayment {
  * id : INT <<PK>>
  --
  user_id : INT <<FK>>
  booking_id : INT <<FK>> (Job)
  stripe_payment_intent_id <<unique>>
  amount_cents, currency, status
}

entity WalletTransaction {
  * id : INT <<PK>>
  --
  user_id : INT <<FK>>
  kind (topup|withdraw)
  amount_cents (signed), stripe_ref
  status (settled counts to balance)
}

entity Coupon {
  * id : INT <<PK>>
  --
  code <<unique>>, scope (system|provider)
  provider_id : INT <<FK>>
  discount_type, discount_value
  starts_at, expires_at, is_active
  created_by : INT <<FK>>
}

entity CouponRedemption {
  * id : INT <<PK>>
  --
  coupon_id : INT <<FK>>
  user_id : INT <<FK>>
  booking_id : INT <<FK>> (Job)
  amount_discounted
}

entity Favorite {
  * id : INT <<PK>>
  --
  user_id : INT <<FK>>
  provider_id : INT <<FK>>
}

entity BrowsingHistory {
  * id : INT <<PK>>
  --
  user_id : INT <<FK>>
  provider_id : INT <<FK>>
  viewed_at
}

User ||--o| ProviderProfile
ServiceCategory ||--o{ ProviderCategory
ProviderProfile ||--o{ ProviderCategory
ProviderProfile ||--o{ ProviderService
User ||--o{ Job : customer
ProviderProfile ||--o{ Job
ServiceCategory ||--o{ Job
Job ||--o| Review
Job ||--o{ Message
User ||--o{ Message : sender
User ||--|| UserCrypto
Job ||--o{ JobCryptoKey
User ||--o{ JobCryptoKey
Message ||--o| HarmMessageReview
User ||--o{ HarmMessageReview : reviewed_by
User ||--o{ StripePayment
Job ||--o{ StripePayment
User ||--o{ WalletTransaction
ProviderProfile ||--o{ Coupon
User ||--o{ Coupon : created_by
Coupon ||--o{ CouponRedemption
Job ||--o{ CouponRedemption
User ||--o{ CouponRedemption
User ||--o{ Favorite
ProviderProfile ||--o{ Favorite
User ||--o{ BrowsingHistory
ProviderProfile ||--o{ BrowsingHistory
@enduml
```

---

## Extra — Sequence Diagram: Per-Interaction Encrypted Channel

> The "encrypt like payment" channel, now also used for chat + order details.
> Reflected live in the floating Encryption Debug capsule.

```plantuml
@startuml SeqSecureChannel
actor User
participant "Client\n(secureTransport)" as C
participant "SecureChannel\nMiddleware" as M
database "enc_session\n+ enc_nonce" as S
participant "Controller" as H

== Handshake (once per session) ==
C -> C : Generate X25519 keypair
C -> M : POST /secure/handshake (client_pub, Bearer JWT)
M -> S : store master/mac keys + salt (TTL)
M --> C : server_pub, salt, session_id, ttl
C -> C : ECDH → HKDF → master + mac keys

== Per sensitive request (payment / chat / order detail) ==
C -> C : derive per-interaction key (HKDF, counter+nonce)\nAES-256-GCM encrypt body, HMAC-sign metadata
C -> M : request + X-Sec-Session/Counter/Nonce/Ts/Sign\n(body in payload, or X-Sec-Body header for GET)
M -> M : check timestamp window
M -> S : claim nonce (atomic replay check)
M -> M : verify HMAC, decrypt body
M -> H : handle(decrypted)
H --> M : plaintext response
M -> M : encrypt response (distinct key, dir=response)
M --> C : ciphertext + X-Sec-Enc: 1
C -> C : decrypt response
@enduml
```

---

## Extra — Key Derivation & Encryption Pipeline (X25519 + HKDF + AES-256-GCM + HMAC)

> Companion to the sequence diagram above: that one shows *who calls whom*; this one shows what
> happens to the *data* — the actual key hierarchy and crypto operations, implemented identically
> in `secureTransport.js` (client) and `SecureChannelMiddleware.php` + `SecureChannel.php` (server).

```plantuml
@startuml CryptoPipeline
skinparam componentStyle rectangle

package "Stage 1 — Handshake (once per ~30 min session)" {
  [Client X25519\nkeypair (ephemeral)] as ClientKP
  [Server X25519\nkeypair (ephemeral)] as ServerKP
  [ECDH shared\nsecret Z] as Z
  [master key\nHKDF(Z, salt, "fixit/v2/master")] as Master
  [mac key\nHKDF(master, salt, "fixit/v2/mac")] as Mac
}

ClientKP -down-> Z : client_pub / server_pub exchanged\nover POST /secure/handshake
ServerKP -down-> Z
Z -down-> Master
Master -down-> Mac

note right of Mac
  master + mac are held in memory only —
  never transmitted again after derivation.
end note

package "Stage 2 — Every request derives its own one-time key" {
  [counter++, random nonce,\ntimestamp] as Ctr
  [request key\nHKDF(master, salt, "fixit/v2/request/{counter}/{nonce}")] as ReqKey
  [AES-256-GCM encrypt\n(body + AAD)] as Enc
  [canonical string\n(session, counter, nonce, ts, method, path, body_hash)] as Canon
  [HMAC-SHA256 sign\n(with mac key)] as Sign
}

Ctr -down-> ReqKey
Master .down.> ReqKey
ReqKey -down-> Enc
Ctr -down-> Canon
Canon -down-> Sign
Mac .down.> Sign

note bottom of Enc
  Output: iv (12B) ‖ ciphertext ‖ tag (16B), base64.
  Sent as the request body, or via X-Sec-Body
  header when the method is GET/HEAD.
end note

package "Server-side verification (in order — cheapest check first)" {
  [1. Timestamp\nwithin window] as V1
  [2. Nonce not\nalready used] as V2
  [3. HMAC signature\nmatches] as V3
  [4. Decrypt with\nthe same derived key] as V4
}
V1 -down-> V2
V2 -down-> V3
V3 -down-> V4

package "Stage 3 — Response uses a DISTINCT key" {
  [response key\nHKDF(master, salt, "fixit/v2/response/{counter}/{nonce}")] as RespKey
  [AES-256-GCM encrypt\n(server's JSON response)] as RespEnc
}
RespKey -down-> RespEnc

note bottom of RespEnc
  Returned with header X-Sec-Enc: 1.
  Client derives the identical response key
  and decrypts — same counter/nonce, different
  HKDF "info" string, so request/sign/response
  keys are all independent within one call.
end note
@enduml
```

**Why this design, in one line each:**
- **X25519 ephemeral keys** → perfect forward secrecy: a leaked session key later can't decrypt past traffic, since each session's keypair is thrown away.
- **HKDF per counter+nonce** → every single request/response gets its own unique key, so no key is ever reused across two messages.
- **AES-256-GCM** → authenticated encryption: tampering with the ciphertext is detected, not just prevented from being read.
- **HMAC over a separate mac key** → the signature proves the *metadata* (path, method, timestamp) wasn't tampered with, independent of whether the body decrypts.
- **Nonce + timestamp window server-side** → replaying a captured request later, even unmodified, is rejected.

---

## Extra — Activity Diagram: Order Status & History Timestamps

> Each status transition stamps a Job timestamp (migration 022); the Order
> Details page renders them as a timeline (customer / provider / admin).

```plantuml
@startuml ActivityOrderHistory
start
:Customer books service;
note right: stamp created_at (submitted)
:Customer pays (Stripe);
note right: StripePayment.created_at (paid_at)
if (Provider accepts?) then (yes)
  :status = accepted;
  note right: stamp accepted_at
  :status = in_progress;
  note right: stamp in_progress_at
  :status = completed;
  note right: stamp completed_at
  :Customer reviews;
  stop
else (cancel)
  :status = cancelled;
  note right: stamp cancelled_at
  stop
endif
@enduml
```

---

## Extra — Sequence Diagram: Direct Chat Notifications (no FCM)

> Client-side only — no Firebase, no push server, no device tokens. Fires while
> the app/tab is alive.

```plantuml
@startuml SeqNotifications
actor User
participant "Client poll\n(push.js, 15s)" as P
participant "Slim API" as API
database "MySQL" as DB
participant "Web/Local\nNotification" as N

loop every 15s while signed in
  P -> API : GET /bookings (Bearer JWT)
  API -> DB : list Jobs + latest_message
  API --> P : bookings[] incl. latest_message
  P -> P : compare latest_message.sent_at\nvs last seen per job
  alt new incoming message (not mine, not system)
    P -> N : show(sender name, preview,\nicon = sender avatar)
    User -> N : tap
    N -> P : open /jobs/{id}/chat
  end
end
@enduml
```
