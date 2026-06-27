// App-wide feature flags.
//
// E2E_ENABLED toggles the end-to-end encrypted chat flow (PIN setup/unlock +
// RSA/AES message encryption). DISABLED by default — chat works as plain text
// without a PIN (keeps admin/CS oversight). The full E2E implementation
// (chatCrypto store, PinModal, crypto service) is kept intact — set
// VITE_E2E_ENABLED=true at build time to turn encryption back on.
export const E2E_ENABLED = (import.meta.env.VITE_E2E_ENABLED ?? 'false') === 'true'
