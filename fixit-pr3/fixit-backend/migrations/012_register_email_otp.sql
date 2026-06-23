-- Registration now verifies the email via OTP before the account exists, so the
-- OTP row has no user yet. Allow a NULL user_id (FK already permits NULL) for
-- pre-registration codes; user-scoped email-change OTPs keep a real user_id.
ALTER TABLE EmailOtp MODIFY user_id INT NULL;
