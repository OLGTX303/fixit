/** Commercial legal documents — keep LEGAL_VERSION in sync with backend migration. */
export const LEGAL_VERSION = '2026-06-19'
export const LEGAL_CONTACT_EMAIL = 'legal@fixit.com'
export const LEGAL_COMPANY = 'FixIt Ltd'

export const TERMS_OF_SERVICE = {
  slug: 'terms',
  title: 'Terms of Service',
  subtitle: 'The agreement between you and FixIt when using our home-services marketplace.',
  lastUpdated: '19 June 2026',
  version: LEGAL_VERSION,
  sections: [
    {
      id: 'acceptance',
      title: '1. Acceptance of Terms',
      paragraphs: [
        'By creating an account, booking a service, offering services as a provider, or otherwise accessing the FixIt platform ("Platform"), you agree to these Terms of Service ("Terms"). If you do not agree, you must not use the Platform.',
        'We may update these Terms from time to time. Material changes will be communicated via the app or email. Continued use after the effective date constitutes acceptance of the revised Terms.',
      ],
    },
    {
      id: 'service',
      title: '2. The FixIt Service',
      paragraphs: [
        'FixIt operates an online marketplace that connects customers seeking home maintenance and related services ("Customers") with independent service professionals ("Providers"). FixIt is not the employer of Providers and does not perform the underlying services.',
        'FixIt facilitates discovery, booking, messaging, identity verification, payments (where enabled), and dispute reporting. We do not guarantee the quality, timing, or outcome of any service performed by a Provider.',
      ],
    },
    {
      id: 'accounts',
      title: '3. Accounts & Eligibility',
      paragraphs: [
        'You must be at least 18 years old and capable of entering a binding contract. You are responsible for maintaining the confidentiality of your credentials and for all activity under your account.',
        'Providers must complete identity verification (KYC), including government ID recognition and face liveness checks, before offering paid services. FixIt may approve, reject, or suspend verification at its discretion.',
        'You agree to provide accurate registration information and to keep your profile up to date.',
      ],
    },
    {
      id: 'bookings',
      title: '4. Bookings, Payments & Cancellations',
      paragraphs: [
        'When you request a booking, you authorise FixIt to share relevant job details with the selected Provider. Pricing, scope, and scheduling are displayed before confirmation where available.',
        'Payments processed through the Platform are handled by third-party payment processors (e.g. Stripe). By paying, you also agree to the processor\'s terms. FixIt may charge service or platform fees as disclosed at checkout.',
        'Cancellation and refund rules depend on job status and Provider policies shown at booking time. Chargebacks made in bad faith may result in account suspension.',
      ],
    },
    {
      id: 'conduct',
      title: '5. User Conduct',
      paragraphs: [
        'You must not: (a) harass, threaten, or discriminate against other users; (b) submit false reviews or fraudulent identity documents; (c) circumvent fees or verification; (d) scrape, reverse-engineer, or overload the Platform; (e) use the Platform for unlawful purposes.',
        'Providers must hold any licences, insurance, or permits required by local law for the services they offer. Customers must provide safe access to the job location and accurate job descriptions.',
      ],
    },
    {
      id: 'content',
      title: '6. Content & Reviews',
      paragraphs: [
        'You retain ownership of content you submit but grant FixIt a worldwide, non-exclusive licence to use, display, and store it for operating the Platform (e.g. profile photos, reviews, chat messages).',
        'Reviews must be honest and based on genuine experiences. We may remove content that violates these Terms or applicable law.',
      ],
    },
    {
      id: 'ip',
      title: '7. Intellectual Property',
      paragraphs: [
        'FixIt and its logos, software, and design are owned by FixIt Ltd or its licensors. You may not copy, modify, or distribute Platform materials without written permission.',
      ],
    },
    {
      id: 'liability',
      title: '8. Disclaimers & Limitation of Liability',
      paragraphs: [
        'THE PLATFORM IS PROVIDED "AS IS" WITHOUT WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, OR NON-INFRINGEMENT. FIXIT DOES NOT WARRANT UNINTERRUPTED OR ERROR-FREE OPERATION.',
        'TO THE MAXIMUM EXTENT PERMITTED BY LAW, FIXIT IS NOT LIABLE FOR INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR FOR LOSS OF PROFITS, DATA, OR GOODWILL. OUR TOTAL LIABILITY FOR ANY CLAIM ARISING FROM THESE TERMS OR THE PLATFORM SHALL NOT EXCEED THE GREATER OF (A) FEES YOU PAID TO FIXIT IN THE 12 MONTHS BEFORE THE CLAIM OR (B) £100.',
        'Nothing in these Terms excludes liability that cannot be excluded under applicable law (including death or personal injury caused by negligence where prohibited).',
      ],
    },
    {
      id: 'termination',
      title: '9. Suspension & Termination',
      paragraphs: [
        'We may suspend or terminate accounts that breach these Terms, pose a safety risk, or are subject to legal requirements. You may close your account at any time by contacting support.',
        'Provisions that by nature should survive termination (including liability limits, dispute resolution, and intellectual property) will remain in effect.',
      ],
    },
    {
      id: 'law',
      title: '10. Governing Law & Disputes',
      paragraphs: [
        'These Terms are governed by the laws of England and Wales, without regard to conflict-of-law principles. Courts in London, England shall have exclusive jurisdiction, except where mandatory consumer protection laws in your country give you the right to bring claims in your local courts.',
        'Before formal proceedings, you agree to contact legal@fixit.com to attempt informal resolution within 30 days.',
      ],
    },
    {
      id: 'contact',
      title: '11. Contact',
      paragraphs: [
        `Questions about these Terms: ${LEGAL_CONTACT_EMAIL}. Registered office: FixIt Ltd, London, United Kingdom.`,
      ],
    },
  ],
}

export const PRIVACY_POLICY = {
  slug: 'privacy',
  title: 'Privacy Policy',
  subtitle: 'How FixIt collects, uses, and protects your personal information.',
  lastUpdated: '19 June 2026',
  version: LEGAL_VERSION,
  sections: [
    {
      id: 'controller',
      title: '1. Who We Are',
      paragraphs: [
        `${LEGAL_COMPANY} ("FixIt", "we", "us") is the data controller for personal information processed through the FixIt mobile and web applications and API.`,
        `Data protection enquiries: ${LEGAL_CONTACT_EMAIL}.`,
      ],
    },
    {
      id: 'collect',
      title: '2. Information We Collect',
      paragraphs: [
        'Account data: name, email, phone, password (stored hashed), role (customer/provider), and profile details.',
        'Identity verification (providers): government ID images, OCR extracts, MRZ validation results, fraud scores, liveness check scores, and verification metadata. ID images are used solely for verification and fraud prevention.',
        'Booking & payment data: job descriptions, addresses, schedules, payment method tokens and last-four digits (processed via Stripe — we do not store full card numbers).',
        'Location data: approximate or precise location when you enable geolocation for search or job matching.',
        'Communications: in-app messages between customers and providers.',
        'Device & usage data: IP address, browser/device type, app version, and security logs.',
      ],
    },
    {
      id: 'use',
      title: '3. How We Use Your Information',
      paragraphs: [
        'We process personal data to: provide and improve the Platform; verify provider identity; process payments; match customers with providers; prevent fraud and abuse; comply with legal obligations; and communicate service updates.',
        'Legal bases (UK GDPR / EU GDPR where applicable): contract performance, legitimate interests (security, fraud prevention, product improvement), legal obligation, and consent where required (e.g. marketing, optional cookies).',
      ],
    },
    {
      id: 'share',
      title: '4. Sharing & Processors',
      paragraphs: [
        'We share data with: service providers (cloud hosting, payment processing, identity verification tooling), other users as needed to fulfil bookings (e.g. Provider sees job address), and authorities when required by law.',
        'We do not sell your personal information. Third-party processors are bound by contractual data-protection obligations.',
      ],
    },
    {
      id: 'retention',
      title: '5. Data Retention',
      paragraphs: [
        'Account data is retained while your account is active and for a reasonable period thereafter for legal, tax, and dispute-resolution purposes.',
        'KYC records are retained as required by anti-fraud and regulatory obligations, typically up to 7 years after account closure unless a shorter period is mandated by law.',
        'Payment records follow financial record-keeping requirements.',
      ],
    },
    {
      id: 'security',
      title: '6. Security',
      paragraphs: [
        'We implement technical and organisational measures including encryption in transit (HTTPS/TLS), hashed passwords, access controls, rate limiting, and security headers. No method of transmission or storage is 100% secure.',
        'Report security concerns to security@fixit.com.',
      ],
    },
    {
      id: 'rights',
      title: '7. Your Rights',
      paragraphs: [
        'Depending on your location, you may have rights to access, rectify, erase, restrict, port, or object to processing of your personal data, and to withdraw consent where processing is consent-based.',
        'To exercise rights, email privacy@fixit.com. You may lodge a complaint with the UK Information Commissioner\'s Office (ICO) or your local supervisory authority.',
      ],
    },
    {
      id: 'international',
      title: '8. International Transfers',
      paragraphs: [
        'Data may be processed in the United Kingdom, European Economic Area, or other countries where our processors operate. We use appropriate safeguards (e.g. Standard Contractual Clauses) where required.',
      ],
    },
    {
      id: 'children',
      title: '9. Children',
      paragraphs: [
        'FixIt is not directed at children under 18. We do not knowingly collect data from minors. Contact us to request deletion if you believe a minor has registered.',
      ],
    },
    {
      id: 'cookies',
      title: '10. Cookies & Local Storage',
      paragraphs: [
        'We use essential session storage for authentication tokens and app preferences. Analytics or marketing cookies, if introduced, will be disclosed with consent options where legally required.',
      ],
    },
    {
      id: 'changes',
      title: '11. Changes to This Policy',
      paragraphs: [
        'We may update this Privacy Policy. The "Last updated" date and version identifier will change accordingly. Material changes will be notified through the Platform or email.',
      ],
    },
    {
      id: 'contact',
      title: '12. Contact',
      paragraphs: [
        `Privacy questions: privacy@fixit.com · ${LEGAL_CONTACT_EMAIL}`,
      ],
    },
  ],
}

export const LEGAL_DOCUMENTS = {
  terms: TERMS_OF_SERVICE,
  privacy: PRIVACY_POLICY,
}