// fixit-provider.jsx — 5 Provider screens

// ─── P1: Profile & Pricing Setup ────────────────────────────
function P1_Profile() {
  const services = ['Pipe Repair', 'Drain Cleaning', 'Leak Detection', 'Installation', 'Boiler Service'];
  return (
    <FXScreen>
      <FXScreenHeader title="My Profile" right={
        <div style={{ background: FX.accentSoft, color: FX.accent, borderRadius: 10, padding: '6px 14px', fontSize: 13, fontWeight: 600 }}>Save</div>
      } />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px' }}>
          {/* Avatar */}
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', paddingBottom: 20 }}>
            <div style={{ position: 'relative', marginBottom: 8 }}>
              <FXAvatar size={76} name="Marcus R" style={{ border: `3px solid ${FX.border}` }} />
              <div style={{ position: 'absolute', bottom: 0, right: 0, width: 26, height: 26, borderRadius: '50%', background: FX.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '2px solid #fff' }}>
                {FXIcons.camera('#fff')}
              </div>
            </div>
            <span style={{ fontSize: 13, color: FX.accent, fontWeight: 500 }}>Change Photo</span>
          </div>

          {/* Fields */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginBottom: 20 }}>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: FX.muted, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 }}>Full Name</div>
              <FXInput value="Marcus Rivera" placeholder="" />
            </div>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: FX.muted, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 }}>Bio</div>
              <div style={{ background: FX.borderSoft, borderRadius: 12, padding: '12px 15px', fontSize: 14, color: FX.text, lineHeight: 1.5 }}>
                Master plumber with 8+ years experience. Specialising in residential repairs and installations.
              </div>
            </div>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: FX.muted, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 }}>Location</div>
              <FXInput value="Greenfield District, Metro City" icon={FXIcons.location(FX.mutedSoft)} />
            </div>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: FX.muted, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 }}>Hourly Rate</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, background: FX.borderSoft, borderRadius: 12, padding: '13px 15px' }}>
                <span style={{ fontSize: 18, fontWeight: 700, color: FX.accent }}>$</span>
                <span style={{ fontSize: 22, fontWeight: 700, color: FX.text }}>45</span>
                <span style={{ fontSize: 14, color: FX.muted }}>/hr</span>
                <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
                  <div style={{ width: 30, height: 30, borderRadius: 8, background: FX.border, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16, fontWeight: 700 }}>−</div>
                  <div style={{ width: 30, height: 30, borderRadius: 8, background: FX.accent, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16, fontWeight: 700 }}>+</div>
                </div>
              </div>
            </div>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: FX.muted, marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.5 }}>Services Offered</div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7 }}>
                {services.map((s, i) => (
                  <FXChip key={s} label={s} active={i < 3} small />
                ))}
                <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4, padding: '4px 10px', borderRadius: 100, border: `1.5px dashed ${FX.border}`, fontSize: 12, color: FX.muted }}>
                  {FXIcons.plus(FX.muted)} Add
                </div>
              </div>
            </div>

            {/* Availability toggle */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fff', borderRadius: 14, padding: '14px 16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' }}>
              <div>
                <div style={{ fontSize: 14, fontWeight: 600 }}>Available for Bookings</div>
                <div style={{ fontSize: 12, color: FX.muted, marginTop: 2 }}>Customers can book your services</div>
              </div>
              <div style={{ width: 44, height: 26, borderRadius: 13, background: FX.success, position: 'relative' }}>
                <div style={{ position: 'absolute', right: 2, top: 2, width: 22, height: 22, borderRadius: '50%', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }} />
              </div>
            </div>
          </div>
        </div>
        <div style={{ padding: '12px 20px', borderTop: `1px solid ${FX.border}`, background: FX.surface, flexShrink: 0 }}>
          <FXPrimaryBtn label="Save Changes" />
        </div>
      </div>
      <FXBottomNav items={PROVIDER_NAV} active={4} />
    </FXScreen>
  );
}

// ─── P2: KYC Document Upload ─────────────────────────────────
function P2_KYC() {
  const steps = ['Personal Info', 'Identity', 'Certifications'];
  return (
    <FXScreen>
      <FXScreenHeader title="Verify Identity" back={true} subtitle="Step 2 of 3" />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* Progress bar */}
        <div style={{ padding: '0 20px 18px' }}>
          <div style={{ display: 'flex', gap: 6, marginBottom: 14 }}>
            {steps.map((s, i) => (
              <div key={s} style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <div style={{ height: 4, borderRadius: 2, background: i <= 1 ? FX.accent : FX.border }} />
                <div style={{ fontSize: 10, color: i <= 1 ? FX.accent : FX.muted, fontWeight: i === 1 ? 700 : 400, textAlign: 'center' }}>{s}</div>
              </div>
            ))}
          </div>
        </div>

        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px' }}>
          {/* Doc type selector */}
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10 }}>Document Type</div>
          <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
            {['Gov. ID', 'Passport', 'License'].map((t, i) => (
              <div key={t} style={{
                flex: 1, padding: '10px 4px', borderRadius: 12, textAlign: 'center',
                fontSize: 13, fontWeight: 500,
                background: i === 0 ? FX.accentSoft : '#fff',
                color: i === 0 ? FX.accent : FX.muted,
                border: `1.5px solid ${i === 0 ? FX.accent : FX.border}`,
              }}>{t}</div>
            ))}
          </div>

          {/* Upload zones */}
          {[
            { label: 'Front of ID', status: 'uploaded' },
            { label: 'Back of ID', status: 'pending' },
          ].map(({ label, status }) => (
            <div key={label} style={{ marginBottom: 14 }}>
              <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>{label}</div>
              <div style={{
                border: `2px dashed ${status === 'uploaded' ? FX.success : FX.border}`,
                borderRadius: 14, padding: '20px 16px',
                display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8,
                background: status === 'uploaded' ? FX.successSoft : FX.borderSoft,
              }}>
                {status === 'uploaded' ? (
                  <>
                    <div style={{ width: 36, height: 36, borderRadius: '50%', background: FX.success, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      {FXIcons.check('#fff')}
                    </div>
                    <span style={{ fontSize: 13, fontWeight: 600, color: FX.success }}>Uploaded successfully</span>
                    <span style={{ fontSize: 12, color: FX.muted }}>id_front.jpg • 1.2 MB</span>
                  </>
                ) : (
                  <>
                    <div style={{ width: 36, height: 36, borderRadius: '50%', background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 1px 4px rgba(0,0,0,0.08)' }}>
                      {FXIcons.upload(FX.muted)}
                    </div>
                    <span style={{ fontSize: 13, color: FX.muted }}>Tap to upload or take photo</span>
                    <span style={{ fontSize: 11, color: FX.mutedSoft }}>PNG, JPG up to 10MB</span>
                  </>
                )}
              </div>
            </div>
          ))}

          {/* Selfie upload */}
          <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>Selfie with ID</div>
            <div style={{ border: `2px dashed ${FX.border}`, borderRadius: 14, padding: '16px', display: 'flex', alignItems: 'center', gap: 12, background: FX.borderSoft }}>
              <div style={{ width: 50, height: 50, borderRadius: '50%', border: `2px dashed ${FX.border}`, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#fff' }}>
                {FXIcons.camera(FX.muted)}
              </div>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: FX.text }}>Take a selfie holding your ID</div>
                <div style={{ fontSize: 11, color: FX.muted, marginTop: 3 }}>Ensure your face and ID are clearly visible</div>
              </div>
            </div>
          </div>

          {/* Privacy note */}
          <div style={{ display: 'flex', gap: 10, background: FX.blueSoft, borderRadius: 12, padding: '12px 14px', marginBottom: 16 }}>
            {FXIcons.shield(FX.blue)}
            <span style={{ fontSize: 12, color: FX.blue, lineHeight: 1.5 }}>
              Your documents are encrypted and only used for identity verification. They are never shared with customers.
            </span>
          </div>
        </div>

        <div style={{ padding: '12px 20px', borderTop: `1px solid ${FX.border}`, background: FX.surface, flexShrink: 0 }}>
          <FXPrimaryBtn label="Continue to Certifications →" />
        </div>
      </div>
      <FXBottomNav items={PROVIDER_NAV} active={4} />
    </FXScreen>
  );
}

// ─── P3: Incoming Booking Requests ───────────────────────────
function P3_Requests() {
  const requests = [
    { name: 'Alex Chen',  service: 'Pipe Repair',    date: 'Jun 11 · 2:00 PM', price: '$90', address: '14 Maple St', urgent: true,  initials: 'AC' },
    { name: 'Sandra M.',  service: 'Drain Cleaning', date: 'Jun 12 · 10:00 AM', price: '$60', address: '8 Oak Ave',   urgent: false, initials: 'SM' },
    { name: 'David K.',   service: 'Leak Detection', date: 'Jun 13 · 9:00 AM',  price: '$75', address: '22 Pine Rd',  urgent: false, initials: 'DK' },
  ];
  return (
    <FXScreen>
      <FXScreenHeader title="Requests" right={
        <FXBadge label="3 new" color={FX.accent} bg={FX.accentSoft} />
      } />
      {/* Tabs */}
      <div style={{ display: 'flex', padding: '0 20px 14px', gap: 0, flexShrink: 0 }}>
        {['New (3)', 'Upcoming', 'Past'].map((t, i) => (
          <div key={t} style={{
            flex: 1, textAlign: 'center', padding: '9px 0',
            fontSize: 13, fontWeight: i === 0 ? 700 : 500,
            color: i === 0 ? FX.accent : FX.muted,
            borderBottom: `2px solid ${i === 0 ? FX.accent : FX.border}`,
          }}>{t}</div>
        ))}
      </div>

      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
          {requests.map((r, i) => (
            <FXCard key={r.name} pad={0} style={{ overflow: 'hidden' }}>
              {r.urgent && (
                <div style={{ background: FX.accentSoft, padding: '5px 14px', display: 'flex', alignItems: 'center', gap: 5 }}>
                  <span style={{ fontSize: 11, color: FX.accent, fontWeight: 600 }}>⚡ Urgent Request</span>
                </div>
              )}
              <div style={{ padding: '12px 14px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                  <div style={{ display: 'flex', gap: 10 }}>
                    <FXAvatar size={40} name={r.name} bg={FX.borderSoft} color={FX.muted} />
                    <div>
                      <div style={{ fontWeight: 600, fontSize: 14 }}>{r.name}</div>
                      <div style={{ fontSize: 12, color: FX.muted }}>{r.service}</div>
                    </div>
                  </div>
                  <div style={{ fontSize: 16, fontWeight: 700, color: FX.accent }}>{r.price}</div>
                </div>
                <div style={{ display: 'flex', gap: 14, marginBottom: 12 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                    {FXIcons.calendar(FX.muted)}
                    <span style={{ fontSize: 12, color: FX.muted }}>{r.date}</span>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                    {FXIcons.location(FX.muted)}
                    <span style={{ fontSize: 12, color: FX.muted }}>{r.address}</span>
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 10 }}>
                  <div style={{ flex: 1, padding: '9px 0', borderRadius: 10, border: `1.5px solid ${FX.error}`, color: FX.error, textAlign: 'center', fontSize: 13, fontWeight: 600 }}>Decline</div>
                  <div style={{ flex: 2, padding: '9px 0', borderRadius: 10, background: FX.accent, color: '#fff', textAlign: 'center', fontSize: 13, fontWeight: 600 }}>Accept Request</div>
                </div>
              </div>
            </FXCard>
          ))}
        </div>
      </div>
      <FXBottomNav items={PROVIDER_NAV} active={1} />
    </FXScreen>
  );
}

// ─── P4: Job Status Update ────────────────────────────────────
function P4_JobUpdate() {
  return (
    <FXScreen>
      <FXScreenHeader title="Active Job" subtitle="#FixIt-2847" back={true} />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column', padding: '0 20px' }}>
        {/* Customer card */}
        <FXCard style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 16 }}>
          <FXAvatar size={46} name="Alex C" bg={FX.blueSoft} color={FX.blue} />
          <div style={{ flex: 1 }}>
            <div style={{ fontWeight: 600 }}>Alex Chen</div>
            <div style={{ fontSize: 12, color: FX.muted }}>Pipe Repair · 14 Maple St</div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.accentSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={FX.accent} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8a19.79 19.79 0 01-3.07-8.67A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.1 6.1l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92l-.08 2z"/></svg>
            </div>
            <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.blueSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              {FXIcons.chat(FX.blue)}
            </div>
          </div>
        </FXCard>

        {/* Timer */}
        <FXCard style={{ background: FX.accent, marginBottom: 16 }} pad={18}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 12, color: 'rgba(255,255,255,0.7)', marginBottom: 4, fontWeight: 500 }}>JOB IN PROGRESS</div>
            <div style={{ fontSize: 44, fontWeight: 800, color: '#fff', letterSpacing: -2, lineHeight: 1 }}>1:23:47</div>
            <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.7)', marginTop: 4 }}>Started at 2:00 PM</div>
          </div>
        </FXCard>

        {/* Status */}
        <div style={{ marginBottom: 14 }}>
          <div style={{ fontSize: 13, fontWeight: 600, color: FX.muted, marginBottom: 8 }}>UPDATE STATUS</div>
          <div style={{ display: 'flex', gap: 8 }}>
            {[
              { label: 'On My Way', icon: '🚗', active: false },
              { label: 'Arrived',   icon: '📍', active: false },
              { label: 'Working',   icon: '🔧', active: true  },
            ].map(s => (
              <div key={s.label} style={{
                flex: 1, padding: '10px 6px', borderRadius: 12, textAlign: 'center',
                background: s.active ? FX.accentSoft : '#fff',
                border: `1.5px solid ${s.active ? FX.accent : FX.border}`,
              }}>
                <div style={{ fontSize: 18, marginBottom: 3 }}>{s.icon}</div>
                <div style={{ fontSize: 11, fontWeight: s.active ? 700 : 500, color: s.active ? FX.accent : FX.muted }}>{s.label}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Notes */}
        <div style={{ fontSize: 13, fontWeight: 600, color: FX.muted, marginBottom: 8 }}>JOB NOTES</div>
        <div style={{ background: FX.borderSoft, borderRadius: 12, padding: '12px 14px', fontSize: 13, color: FX.muted, lineHeight: 1.5, marginBottom: 14 }}>
          Leaking pipe under kitchen sink. Customer reports water damage to cabinet floor.
        </div>

        <FXPrimaryBtn label="✓ Mark as Complete" accent={FX.success} />
      </div>
      <FXBottomNav items={PROVIDER_NAV} active={2} />
    </FXScreen>
  );
}

// ─── P5: In-App Chat ─────────────────────────────────────────
function P5_Chat() {
  const msgs = [
    { from: 'customer', text: "Hi Marcus, when will you arrive?", time: '1:42 PM' },
    { from: 'provider', text: "On my way now, about 10 minutes!", time: '1:44 PM' },
    { from: 'customer', text: "Great, the front door is open for you.", time: '1:45 PM' },
    { from: 'provider', text: "Perfect, see you soon 👍", time: '1:46 PM' },
    { from: 'customer', text: "The leak is under the kitchen sink, I'll show you when you get here.", time: '1:47 PM' },
    { from: 'provider', text: "Got it, I have all the tools needed for a pipe repair. Be there shortly.", time: '1:49 PM' },
  ];
  return (
    <FXScreen bg="#fff">
      {/* Chat header */}
      <div style={{ flexShrink: 0, padding: '0 20px 12px', borderBottom: `1px solid ${FX.border}`, display: 'flex', alignItems: 'center', gap: 12 }}>
        <div style={{ width: 32, height: 32, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          {FXIcons.back(FX.text)}
        </div>
        <div style={{ position: 'relative' }}>
          <FXAvatar size={40} name="Alex C" bg={FX.blueSoft} color={FX.blue} />
          <div style={{ position: 'absolute', bottom: 1, right: 1, width: 10, height: 10, borderRadius: '50%', background: FX.success, border: '2px solid #fff' }} />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 700, fontSize: 15 }}>Alex Chen</div>
          <div style={{ fontSize: 12, color: FX.success, fontWeight: 500 }}>Online · Booking #FixIt-2847</div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={FX.muted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8a19.79 19.79 0 01-3.07-8.67A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.1 6.1l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92l-.08 2z"/></svg>
          </div>
        </div>
      </div>

      {/* Messages */}
      <div style={{ flex: 1, overflow: 'hidden', padding: '14px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        {msgs.map((m, i) => (
          <div key={i} style={{ display: 'flex', justifyContent: m.from === 'provider' ? 'flex-end' : 'flex-start', gap: 8, alignItems: 'flex-end' }}>
            {m.from === 'customer' && <FXAvatar size={28} name="Alex C" bg={FX.blueSoft} color={FX.blue} />}
            <div style={{ maxWidth: '72%' }}>
              <div style={{
                padding: '10px 13px', borderRadius: 16,
                borderBottomLeftRadius: m.from === 'customer' ? 4 : 16,
                borderBottomRightRadius: m.from === 'provider' ? 4 : 16,
                background: m.from === 'provider' ? FX.accent : FX.borderSoft,
                color: m.from === 'provider' ? '#fff' : FX.text,
                fontSize: 13, lineHeight: 1.5,
              }}>{m.text}</div>
              <div style={{ fontSize: 10, color: FX.mutedSoft, marginTop: 3, textAlign: m.from === 'provider' ? 'right' : 'left' }}>{m.time}</div>
            </div>
            {m.from === 'provider' && <FXAvatar size={28} name="Marcus R" />}
          </div>
        ))}
      </div>

      {/* Input bar */}
      <div style={{ flexShrink: 0, padding: '10px 16px 14px', borderTop: `1px solid ${FX.border}`, display: 'flex', gap: 10, alignItems: 'center', background: '#fff' }}>
        <div style={{ flex: 1, background: FX.borderSoft, borderRadius: 22, padding: '11px 16px', fontSize: 14, color: FX.mutedSoft }}>
          Type a message...
        </div>
        <div style={{ width: 40, height: 40, borderRadius: '50%', background: FX.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
          {FXIcons.send('#fff')}
        </div>
      </div>
      <FXBottomNav items={PROVIDER_NAV} active={3} />
    </FXScreen>
  );
}

Object.assign(window, { P1_Profile, P2_KYC, P3_Requests, P4_JobUpdate, P5_Chat });
