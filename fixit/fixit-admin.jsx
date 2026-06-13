// fixit-admin.jsx — 3 Admin screens

// ─── A1: Provider Verification Dashboard ────────────────────
function A1_Verification() {
  const stats = [
    { label: 'Pending',  value: 12, color: FX.warn,    bg: FX.warnSoft  },
    { label: 'Approved', value: 234, color: FX.success, bg: FX.successSoft },
    { label: 'Rejected', value: 8,   color: FX.error,   bg: FX.errorSoft },
  ];
  const providers = [
    { name: 'James O.',  role: 'Electrician', docs: 'Complete',  kyc: 'Pending',  date: 'Jun 9',  initials: 'JO' },
    { name: 'Rosa T.',   role: 'Cleaner',     docs: 'Partial',   kyc: 'Pending',  date: 'Jun 10', initials: 'RT' },
    { name: 'Leon M.',   role: 'Gardener',    docs: 'Complete',  kyc: 'Approved', date: 'Jun 8',  initials: 'LM' },
    { name: 'Aisha N.',  role: 'Plumber',     docs: 'Complete',  kyc: 'Rejected', date: 'Jun 7',  initials: 'AN' },
  ];

  const kycColor = { Pending: { c: FX.warn, bg: FX.warnSoft }, Approved: { c: FX.success, bg: FX.successSoft }, Rejected: { c: FX.error, bg: FX.errorSoft } };

  return (
    <FXScreen>
      <FXScreenHeader title="Verifications" subtitle="Admin Dashboard" right={
        <FXAvatar size={34} name="Admin" bg={FX.accentSoft} color={FX.accent} />
      } />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* Stats */}
        <div style={{ display: 'flex', gap: 10, padding: '0 20px 16px' }}>
          {stats.map(s => (
            <div key={s.label} style={{ flex: 1, background: s.bg, borderRadius: 14, padding: '12px 10px', textAlign: 'center' }}>
              <div style={{ fontSize: 24, fontWeight: 800, color: s.color }}>{s.value}</div>
              <div style={{ fontSize: 11, color: s.color, fontWeight: 500, marginTop: 2 }}>{s.label}</div>
            </div>
          ))}
        </div>

        {/* Filter tabs */}
        <div style={{ display: 'flex', padding: '0 20px 12px', gap: 0 }}>
          {['All', 'Pending', 'Approved', 'Rejected'].map((t, i) => (
            <div key={t} style={{
              flex: 1, textAlign: 'center', padding: '8px 0', fontSize: 12,
              fontWeight: i === 1 ? 700 : 500,
              color: i === 1 ? FX.accent : FX.muted,
              borderBottom: `2px solid ${i === 1 ? FX.accent : FX.border}`,
            }}>{t}</div>
          ))}
        </div>

        {/* Provider list */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
          {providers.map(p => {
            const kc = kycColor[p.kyc];
            return (
              <FXCard key={p.name} pad={12}>
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                  <FXAvatar size={42} name={p.name} bg={FX.borderSoft} color={FX.muted} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontWeight: 600, fontSize: 14 }}>{p.name}</span>
                      <FXBadge label={p.kyc} color={kc.c} bg={kc.bg} />
                    </div>
                    <div style={{ fontSize: 12, color: FX.muted, marginTop: 2 }}>{p.role} · Applied {p.date}</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 5 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                        <div style={{ width: 8, height: 8, borderRadius: '50%', background: p.docs === 'Complete' ? FX.success : FX.warn }} />
                        <span style={{ fontSize: 11, color: FX.muted }}>Docs: {p.docs}</span>
                      </div>
                    </div>
                    {p.kyc === 'Pending' && (
                      <div style={{ display: 'flex', gap: 7, marginTop: 8 }}>
                        <div style={{ flex: 1, padding: '7px 0', borderRadius: 9, border: `1.5px solid ${FX.error}`, color: FX.error, textAlign: 'center', fontSize: 12, fontWeight: 600 }}>Reject</div>
                        <div style={{ flex: 2, padding: '7px 0', borderRadius: 9, background: FX.accent, color: '#fff', textAlign: 'center', fontSize: 12, fontWeight: 600 }}>Review & Approve</div>
                      </div>
                    )}
                  </div>
                </div>
              </FXCard>
            );
          })}
        </div>
      </div>
      <FXBottomNav items={ADMIN_NAV} active={1} />
    </FXScreen>
  );
}

// ─── A2: User & Category Management ─────────────────────────
function A2_Management() {
  const users = [
    { name: 'Alex Chen',  role: 'Customer', status: 'Active',   joined: 'Mar 2024' },
    { name: 'Marcus R.',  role: 'Provider', status: 'Active',   joined: 'Jan 2024' },
    { name: 'Sandra M.',  role: 'Customer', status: 'Inactive', joined: 'Apr 2024' },
    { name: 'James O.',   role: 'Provider', status: 'Pending',  joined: 'Jun 2024' },
  ];
  const categories = [
    { name: 'Plumbing',    icon: '🔧', count: 48, active: true  },
    { name: 'Electrical',  icon: '⚡', count: 32, active: true  },
    { name: 'Cleaning',    icon: '🧹', count: 61, active: true  },
    { name: 'Gardening',   icon: '🌱', count: 27, active: true  },
    { name: 'AC Service',  icon: '❄️', count: 19, active: false },
    { name: 'Moving',      icon: '📦', count: 14, active: true  },
  ];
  const statusColor = { Active: { c: FX.success, bg: FX.successSoft }, Inactive: { c: FX.muted, bg: FX.borderSoft }, Pending: { c: FX.warn, bg: FX.warnSoft } };

  return (
    <FXScreen>
      <FXScreenHeader title="Management" right={
        <div style={{ width: 32, height: 32, borderRadius: '50%', background: FX.accentSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          {FXIcons.plus(FX.accent)}
        </div>
      } />

      {/* Tab toggle */}
      <div style={{ display: 'flex', margin: '0 20px 14px', background: FX.borderSoft, borderRadius: 12, padding: 3, flexShrink: 0 }}>
        {['Users', 'Categories'].map((t, i) => (
          <div key={t} style={{
            flex: 1, padding: '8px 0', borderRadius: 9, textAlign: 'center',
            fontSize: 13, fontWeight: 600,
            background: i === 0 ? '#fff' : 'transparent',
            color: i === 0 ? FX.text : FX.muted,
            boxShadow: i === 0 ? '0 1px 4px rgba(0,0,0,0.08)' : 'none',
          }}>{t}</div>
        ))}
      </div>

      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* Search */}
        <div style={{ padding: '0 20px 12px', flexShrink: 0 }}>
          <FXInput placeholder="Search users..." icon={FXIcons.search(FX.mutedSoft)} style={{ background: '#fff', boxShadow: '0 1px 4px rgba(0,0,0,0.05)', borderRadius: 12 }} />
        </div>

        {/* Summary row */}
        <div style={{ display: 'flex', gap: 10, padding: '0 20px 12px', flexShrink: 0 }}>
          {[['1,284', 'Total'], ['847', 'Customers'], ['437', 'Providers']].map(([v, l]) => (
            <div key={l} style={{ flex: 1, background: '#fff', borderRadius: 12, padding: '10px 8px', textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <div style={{ fontSize: 18, fontWeight: 800, color: FX.text }}>{v}</div>
              <div style={{ fontSize: 10, color: FX.muted, marginTop: 1 }}>{l}</div>
            </div>
          ))}
        </div>

        {/* Users list */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px', display: 'flex', flexDirection: 'column', gap: 8 }}>
          {users.map(u => {
            const sc = statusColor[u.status];
            return (
              <div key={u.name} style={{ background: '#fff', borderRadius: 12, padding: '11px 13px', display: 'flex', alignItems: 'center', gap: 10, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
                <FXAvatar size={38} name={u.name} bg={u.role === 'Provider' ? FX.accentSoft : FX.blueSoft} color={u.role === 'Provider' ? FX.accent : FX.blue} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600, fontSize: 13 }}>{u.name}</div>
                  <div style={{ fontSize: 11, color: FX.muted }}>{u.role} · {u.joined}</div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <FXBadge label={u.status} color={sc.c} bg={sc.bg} size={10} />
                  <div style={{ width: 24, height: 24, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {FXIcons.edit(FX.muted)}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
      <FXBottomNav items={ADMIN_NAV} active={2} />
    </FXScreen>
  );
}

// ─── A3: Booking & Review Monitoring ────────────────────────
function A3_Monitoring() {
  const bookings = [
    { id: '#2847', customer: 'Alex C.',  provider: 'Marcus R.', service: 'Plumbing',   status: 'Active',    amount: '$90',  flag: false },
    { id: '#2846', customer: 'Sandra M.',provider: 'Priya S.', service: 'Electrical', status: 'Completed', amount: '$120', flag: false },
    { id: '#2845', customer: 'David K.', provider: 'Tom W.',   service: 'Cleaning',   status: 'Disputed',  amount: '$60',  flag: true  },
    { id: '#2844', customer: 'Lucy P.',  provider: 'Jake B.',  service: 'Plumbing',   status: 'Completed', amount: '$80',  flag: false },
  ];
  const statusColor = {
    Active:    { c: FX.blue,    bg: FX.blueSoft    },
    Completed: { c: FX.success, bg: FX.successSoft },
    Disputed:  { c: FX.error,   bg: FX.errorSoft   },
  };
  return (
    <FXScreen>
      <FXScreenHeader title="Monitoring" subtitle="Bookings & Reviews" />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* KPI row */}
        <div style={{ display: 'flex', gap: 8, padding: '0 20px 16px' }}>
          {[
            { label: 'Bookings', value: '847',  icon: '📅', bg: FX.blueSoft,    color: FX.blue    },
            { label: 'Disputes', value: '3',    icon: '⚠️', bg: FX.errorSoft,   color: FX.error   },
            { label: 'Avg Rating', value: '4.7', icon: '⭐', bg: FX.warnSoft,   color: FX.warn    },
          ].map(k => (
            <div key={k.label} style={{ flex: 1, background: k.bg, borderRadius: 14, padding: '10px 8px', textAlign: 'center' }}>
              <div style={{ fontSize: 16 }}>{k.icon}</div>
              <div style={{ fontSize: 20, fontWeight: 800, color: k.color, lineHeight: 1.2 }}>{k.value}</div>
              <div style={{ fontSize: 10, color: k.color, fontWeight: 500, marginTop: 2, opacity: 0.8 }}>{k.label}</div>
            </div>
          ))}
        </div>

        {/* Filter row */}
        <div style={{ display: 'flex', gap: 8, padding: '0 20px 12px', overflowX: 'auto', flexShrink: 0 }}>
          {['All', 'Active', 'Completed', 'Disputed'].map((f, i) => (
            <FXChip key={f} label={f} active={i === 0} small />
          ))}
        </div>

        {/* Bookings list */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px', display: 'flex', flexDirection: 'column', gap: 8 }}>
          {bookings.map(b => {
            const sc = statusColor[b.status];
            return (
              <FXCard key={b.id} pad={12}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 6 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span style={{ fontSize: 13, fontWeight: 700, color: FX.text }}>{b.id}</span>
                    {b.flag && <span style={{ fontSize: 11, background: FX.errorSoft, color: FX.error, padding: '2px 6px', borderRadius: 6, fontWeight: 600 }}>⚠ Flagged</span>}
                  </div>
                  <span style={{ fontSize: 15, fontWeight: 700, color: FX.text }}>{b.amount}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <div style={{ fontSize: 12, color: FX.muted }}>
                      <span style={{ fontWeight: 500, color: FX.text }}>{b.customer}</span> → <span style={{ fontWeight: 500, color: FX.text }}>{b.provider}</span>
                    </div>
                    <div style={{ fontSize: 11, color: FX.muted, marginTop: 2 }}>{b.service}</div>
                  </div>
                  <FXBadge label={b.status} color={sc.c} bg={sc.bg} size={10} />
                </div>
                {b.flag && (
                  <div style={{ display: 'flex', gap: 7, marginTop: 8 }}>
                    <div style={{ flex: 1, padding: '7px 0', borderRadius: 8, border: `1.5px solid ${FX.border}`, color: FX.muted, textAlign: 'center', fontSize: 12, fontWeight: 600 }}>View Details</div>
                    <div style={{ flex: 1, padding: '7px 0', borderRadius: 8, background: FX.error, color: '#fff', textAlign: 'center', fontSize: 12, fontWeight: 600 }}>Resolve</div>
                  </div>
                )}
              </FXCard>
            );
          })}

          {/* Flagged reviews section */}
          <div style={{ marginTop: 6 }}>
            <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10, display: 'flex', justifyContent: 'space-between' }}>
              <span>Flagged Reviews</span>
              <FXBadge label="2 flagged" color={FX.error} bg={FX.errorSoft} />
            </div>
            {[
              { reviewer: 'David K.', provider: 'Tom W.', rating: 1, excerpt: '"Never showed up and refused to refund..."' },
            ].map((r, i) => (
              <FXCard key={i} pad={12} style={{ borderLeft: `3px solid ${FX.error}` }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                  <span style={{ fontSize: 13, fontWeight: 600 }}>{r.reviewer} → {r.provider}</span>
                  <FXStars rating={r.rating} size={11} />
                </div>
                <div style={{ fontSize: 12, color: FX.muted, fontStyle: 'italic', marginBottom: 8 }}>{r.excerpt}</div>
                <div style={{ display: 'flex', gap: 7 }}>
                  <div style={{ flex: 1, padding: '6px 0', borderRadius: 8, border: `1.5px solid ${FX.border}`, color: FX.muted, textAlign: 'center', fontSize: 11, fontWeight: 600 }}>Keep</div>
                  <div style={{ flex: 1, padding: '6px 0', borderRadius: 8, background: FX.error, color: '#fff', textAlign: 'center', fontSize: 11, fontWeight: 600 }}>Remove</div>
                </div>
              </FXCard>
            ))}
          </div>
        </div>
      </div>
      <FXBottomNav items={ADMIN_NAV} active={3} />
    </FXScreen>
  );
}

Object.assign(window, { A1_Verification, A2_Management, A3_Monitoring });
