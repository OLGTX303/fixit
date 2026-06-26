-- FixIt PR3 — seed data (password for all users: password123)
-- West Malaysia market: primary users in Johor Bahru + Skudai (UTM).
USE fixit;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE HarmMessageReview;
TRUNCATE TABLE JobCryptoKey;
TRUNCATE TABLE UserCrypto;
TRUNCATE TABLE EmailOtp;
TRUNCATE TABLE Message;
TRUNCATE TABLE Review;
TRUNCATE TABLE Job;
TRUNCATE TABLE ProviderCategory;
TRUNCATE TABLE ProviderProfile;
TRUNCATE TABLE ServiceCategory;
TRUNCATE TABLE User;
SET FOREIGN_KEY_CHECKS = 1;

-- bcrypt hash for password123
SET @pw = '$2b$10$W8uJ9XXqGA48SFIvvC7qjOTKzlUlJWrmaUQtjli4z6EfMsHDDM2Nu';

INSERT INTO User (id, name, email, password_hash, role, phone, location_label, region, latitude, longitude) VALUES
(1,  'Alex Chen',      'alex@email.com',    @pw, 'customer', '+60 12-345 6701', 'Skudai · UTM',           'skudai_utm',  1.55950000, 103.63830000),
(2,  'Siti Nuraina',   'sandra@email.com',  @pw, 'customer', '+60 13-456 7802', 'Taman Pelangi, JB',      'johor_bahru', 1.48550000, 103.76200000),
(3,  'David Kim',      'david@email.com',   @pw, 'customer', '+60 16-567 8903', 'Taman Molek, JB',         'johor_bahru', 1.52760000, 103.76000000),
(4,  'Razak Ibrahim',  'marcus@email.com',  @pw, 'provider', '+60 17-678 9004', 'Skudai (near UTM)',      'skudai_utm',  1.55400000, 103.64200000),
(5,  'Lim Mei Ling',   'priya@email.com',   @pw, 'provider', '+60 12-789 0105', 'Taman Pelangi, JB',      'johor_bahru', 1.48550000, 103.76200000),
(6,  'Kumar Raj',      'tom@email.com',     @pw, 'provider', '+60 19-890 1206', 'Taman Universiti, Skudai','skudai_utm',  1.56100000, 103.63500000),
(7,  'Hafiz Rahman',   'leon@email.com',    @pw, 'provider', '+60 11-2345 6707', 'JB City Centre',        'johor_bahru', 1.46550000, 103.75780000),
(8,  'Wong Ah Kau',    'james@email.com',   @pw, 'provider', '+60 14-901 2308', 'Kempas, JB',              'johor_bahru', 1.53600000, 103.71800000),
(9,  'Nurul Aina',     'rosa@email.com',    @pw, 'provider', '+60 18-012 3409', 'UTM Area, Skudai',       'skudai_utm',  1.55700000, 103.64000000),
(10, 'Admin One',      'admin@fixit.com',   @pw, 'admin',    '+60 12-000 0010', 'Johor Bahru HQ',         'johor_bahru', 1.49270000, 103.74140000),
(11, 'Admin Two',      'ops@fixit.com',     @pw, 'admin',    '+60 12-000 0011', 'Skudai · UTM',           'skudai_utm',  1.55950000, 103.63830000);

INSERT INTO ServiceCategory (id, name, description, icon_url) VALUES
(1, 'Plumbing',   'Pipe repair, leaks, installations', '🔧'),
(2, 'Electrical', 'Wiring, fixtures, fault finding',    '⚡'),
(3, 'Cleaning',   'Deep cleans and regular service',    '🧹'),
(4, 'Gardening',  'Lawn care, hedges, landscaping',      '🌱'),
(5, 'AC Service', 'Install, repair and maintenance',     '❄️'),
(6, 'Moving',     'Local moves and heavy lifting',       '📦');

-- Providers: Skudai/UTM cluster + Johor Bahru metro (separate GPS pockets).
INSERT INTO ProviderProfile (id, user_id, bio, location, base_rate, is_verified, kyc_doc_url, avg_rating, latitude, longitude, services_json) VALUES
(1, 4, 'Master plumber with 8+ years experience. Based near UTM Skudai.', 'Skudai (near UTM)', 45.00, 1, '/uploads/kyc/marcus_id.jpg', 4.90, 1.55400000, 103.64200000, '["Pipe Repair","Drain Cleaning","Leak Detection","Installation"]'),
(2, 5, 'Certified electrician. Domestic rewiring, fault finding and smart-home installs.', 'Taman Pelangi, Johor Bahru', 52.00, 1, '/uploads/kyc/priya_id.jpg', 4.80, 1.48550000, 103.76200000, '["Wiring","Fuse Box","Lighting","Fault Finding"]'),
(3, 6, 'Reliable deep-clean specialist. Serves UTM campus and Taman Universiti.', 'Taman Universiti, Skudai', 35.00, 1, '/uploads/kyc/tom_id.jpg', 4.70, 1.56100000, 103.63500000, '["Deep Clean","End of Tenancy","Regular Clean","Carpets"]'),
(4, 7, 'Gardener and landscaper. Lawn care, hedge trimming and seasonal tidy-ups.', 'Johor Bahru City Centre', 40.00, 1, '/uploads/kyc/leon_id.jpg', 4.60, 1.46550000, 103.75780000, '["Lawn Care","Hedge Trimming","Planting","Clearance"]'),
(5, 8, 'Electrician specialising in AC units and ventilation. Awaiting verification.', 'Kempas, Johor Bahru', 48.00, 0, '/uploads/kyc/james_id.jpg', 0.00, 1.53600000, 103.71800000, '["AC Install","AC Repair","Ventilation"]'),
(6, 9, 'Home cleaner offering eco-friendly products. Awaiting verification.', 'UTM Area, Skudai', 30.00, 0, '/uploads/kyc/rosa_id.jpg', 0.00, 1.55700000, 103.64000000, '["Regular Clean","Eco Clean","Ironing"]');

INSERT INTO ProviderCategory (provider_id, category_id) VALUES
(1, 1), (2, 2), (3, 3), (4, 4), (5, 2), (5, 5), (6, 3);

INSERT INTO Job (id, customer_id, provider_id, category_id, status, scheduled_at, address, total, notes) VALUES
(2847, 1, 1, 1, 'in_progress', '2026-06-11 14:00:00', 'Jalan Sultanah Aminah, Taman Universiti, 81300 Skudai, Johor', 95.00, 'Leaking pipe under kitchen sink. Customer reports water damage to cabinet floor.'),
(2846, 2, 2, 2, 'completed',   '2026-06-08 10:00:00', '8, Jalan Molek 2/3, Taman Molek, 81100 Johor Bahru', 120.00, 'Replace consumer unit and test circuits.'),
(2845, 3, 3, 3, 'reviewed',    '2026-06-05 13:00:00', '22, Jalan Pelangi 5, Taman Pelangi, 80400 Johor Bahru', 60.00, 'End-of-tenancy deep clean, two-bed unit.'),
(2848, 1, 4, 4, 'requested',   '2026-06-15 09:00:00', 'Jalan Sultanah Aminah, Taman Universiti, 81300 Skudai, Johor', 80.00, 'Front lawn overgrown, needs cut and hedge trim.');

INSERT INTO Review (id, job_id, rating, comment, created_at) VALUES
(1, 2845, 5, 'End-of-tenancy clean was spotless. Got my full deposit back!', '2026-06-05 18:30:00'),
(2, 2846, 5, 'Mei Ling rewired the kitchen safely and tidily. Highly recommended.', '2026-06-08 16:10:00'),
(3, 2846, 4, 'Great work, fair price. Arrived a little late but kept me informed.', '2026-06-09 09:05:00'),
(4, 2845, 5, 'Fixed our burst pipe in under an hour. Incredibly professional!', '2026-06-06 11:20:00'),
(5, 2845, 5, 'Friendly, on time, and left the place cleaner than before.', '2026-06-07 14:45:00');

INSERT INTO Message (id, job_id, sender_id, body, sent_at) VALUES
(1, 2847, 1, 'Hi Razak, when will you arrive?', '2026-06-11 13:42:00'),
(2, 2847, 4, 'On my way now, about 10 minutes!', '2026-06-11 13:44:00'),
(3, 2847, 1, 'Great, the front door is open for you.', '2026-06-11 13:45:00'),
(4, 2847, 4, 'Perfect, see you soon 👍', '2026-06-11 13:46:00'),
(5, 2847, 1, 'The leak is under the kitchen sink, I''ll show you when you get here.', '2026-06-11 13:47:00'),
(6, 2847, 4, 'Got it, I have all the tools needed for a pipe repair. Be there shortly.', '2026-06-11 13:49:00');