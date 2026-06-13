// fixit-customer.jsx — 7 Customer screens

// ─── C1: Login / Register ────────────────────────────────────
function C1_Login({ mode = 'login' }) {
  return (
    <FXScreen bg="#fff">
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '0 28px', overflow: 'hidden' }}>
        {/* Logo */}
        <div style={{ paddingTop: 16, paddingBottom: 28 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
            <div style={{ width: 42, height: 42, borderRadius: 12, background: FX.accent, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>
              </svg>
            </div>
            <span style={{ fontSize: 26, fontWeight: 800, color: FX.text, letterSpacing: -0.5 }}>FixIt</span>
          </div>
          <div style={{ fontSize: 14, color: FX.muted }}>Your home, perfectly maintained.</div>
        </div>

        {/* Tab toggle */}
        <div style={{ display: 'flex', background: FX.borderSoft, borderRadius: 12, padding: 4, marginBottom: 24 }}>
          {['Login', 'Register'].map((t, i) => (
            <div key={t} style={{
              flex: 1, padding: '9px 0', borderRadius: 9, textAlign: 'center',
              fontSize: 14, fontWeight: 600,
              background: (mode === 'login') === (i === 0) ? '#fff' : 'transparent',
              color: (mode === 'login') === (i === 0) ? FX.text : FX.muted,
              boxShadow: (mode === 'login') === (i === 0) ? '0 1px 4px rgba(0,0,0,0.1)' : 'none',
            }}>{t}</div>
          ))}
        </div>

        {/* Fields */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginBottom: 8 }}>
          {mode === 'register' && (
            <FXInput placeholder="Full name" icon={FXIcons.user('#9CA3AF')} />
          )}
          <FXInput placeholder="Email address" value="alex@email.com" icon={
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          } />
          <FXInput placeholder="Password" value="••••••••" icon={
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          } />
        </div>

        {mode === 'login' && (
          <div style={{ textAlign: 'right', marginBottom: 20 }}>
            <span style={{ fontSize: 13, color: FX.accent, fontWeight: 500 }}>Forgot password?</span>
          </div>
        )}

        <div style={{ marginBottom: 20, marginTop: mode === 'register' ? 16 : 0 }}>
          <FXPrimaryBtn label={mode === 'login' ? 'Sign In' : 'Create Account'} />
        </div>

        {/* Divider */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
          <div style={{ flex: 1, height: 1, background: FX.border }} />
          <span style={{ fontSize: 13, color: FX.mutedSoft }}>or continue with</span>
          <div style={{ flex: 1, height: 1, background: FX.border }} />
        </div>

        {/* Social */}
        <div style={{ display: 'flex', gap: 12, marginBottom: 28 }}>
          {[
            { label: 'Apple', icon: <svg width="18" height="18" viewBox="0 0 24 24" fill={FX.text}><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg> },
            { label: 'Google', icon: <svg width="18" height="18" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> },
          ].map(({ label, icon }) => (
            <div key={label} style={{
              flex: 1, padding: '11px 0', borderRadius: 12,
              border: `1.5px solid ${FX.border}`, display: 'flex',
              alignItems: 'center', justifyContent: 'center', gap: 8,
              fontSize: 14, fontWeight: 500, color: FX.text,
            }}>
              {icon} {label}
            </div>
          ))}
        </div>

        <div style={{ textAlign: 'center', fontSize: 13, color: FX.muted }}>
          {mode === 'login' ? "Don't have an account? " : 'Already have an account? '}
          <span style={{ color: FX.accent, fontWeight: 600 }}>{mode === 'login' ? 'Sign up' : 'Sign in'}</span>
        </div>
      </div>
    </FXScreen>
  );
}

// ─── C2: Browse Categories ───────────────────────────────────
function C2_Browse() {
  const cats = [
    { label: 'Plumbing',    color: '#EFF6FF', icon: '🔧', textColor: '#1D4ED8' },
    { label: 'Electrical',  color: '#FFFBEB', icon: '⚡', textColor: '#B45309' },
    { label: 'Cleaning',    color: '#F0FDF4', icon: '🧹', textColor: '#15803D' },
    { label: 'Gardening',   color: '#F0FDF4', icon: '🌱', textColor: '#166534' },
    { label: 'AC Service',  color: '#EFF6FF', icon: '❄️', textColor: '#1E40AF' },
    { label: 'More',        color: FX.accentSoft, icon: '⋯', textColor: FX.accent },
  ];
  const providers = [
    { name: 'Marcus R.',  role: 'Plumber',      rating: 4.9, jobs: 143, dist: '0.8km' },
    { name: 'Priya S.',   role: 'Electrician',  rating: 4.8, jobs: 98,  dist: '1.2km' },
    { name: 'Tom W.',     role: 'Cleaner',      rating: 4.7, jobs: 211, dist: '0.5km' },
  ];
  return (
    <FXScreen>
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* Header */}
        <div style={{ padding: '8px 20px 14px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <div style={{ fontSize: 22, fontWeight: 800, color: FX.text }}>Good morning, Alex</div>
            <div style={{ fontSize: 14, color: FX.muted, marginTop: 2 }}>What do you need fixed today?</div>
          </div>
          <div style={{ position: 'relative' }}>
            <div style={{ width: 40, height: 40, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              {FXIcons.bell(FX.muted)}
            </div>
            <div style={{ position: 'absolute', top: 0, right: 0, width: 10, height: 10, borderRadius: '50%', background: FX.accent, border: '2px solid #F7F8FA' }} />
          </div>
        </div>

        {/* Search */}
        <div style={{ padding: '0 20px 16px' }}>
          <FXInput placeholder="Search services or providers..." icon={FXIcons.search('#9CA3AF')} style={{ background: '#fff', boxShadow: '0 1px 6px rgba(0,0,0,0.06)', borderRadius: 14 }} />
        </div>

        {/* Categories grid */}
        <div style={{ padding: '0 20px 8px' }}>
          <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 12 }}>Service Categories</div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10 }}>
            {cats.map(c => (
              <div key={c.label} style={{ background: c.color, borderRadius: 14, padding: '14px 10px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
                <span style={{ fontSize: 24 }}>{c.icon}</span>
                <span style={{ fontSize: 12, fontWeight: 600, color: c.textColor, textAlign: 'center' }}>{c.label}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Providers */}
        <div style={{ padding: '12px 20px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
          <span style={{ fontSize: 16, fontWeight: 700 }}>Top Rated Nearby</span>
          <span style={{ fontSize: 13, color: FX.accent, fontWeight: 500 }}>See all</span>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '0 20px' }}>
          {providers.map(p => (
            <FXCard key={p.name} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <FXAvatar size={44} name={p.name} />
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 600, fontSize: 14 }}>{p.name}</div>
                <div style={{ fontSize: 12, color: FX.muted }}>{p.role} • {p.jobs} jobs</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 3, justifyContent: 'flex-end' }}>
                  {FXIcons.star('#FBBF24')}
                  <span style={{ fontSize: 13, fontWeight: 600 }}>{p.rating}</span>
                </div>
                <div style={{ fontSize: 12, color: FX.muted }}>{p.dist}</div>
              </div>
            </FXCard>
          ))}
        </div>
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={0} />
    </FXScreen>
  );
}

// ─── C3: Provider Search + Map ───────────────────────────────
function C3_Search() {
  const pins = [
    { x: 80,  y: 90,  name: 'Marcus R.', rate: '$45', rating: 4.9, active: true  },
    { x: 180, y: 130, name: 'Jake B.',   rate: '$38', rating: 4.6, active: false },
    { x: 290, y: 80,  name: 'Leila F.',  rate: '$52', rating: 4.8, active: false },
    { x: 140, y: 190, name: 'Omar S.',   rate: '$40', rating: 4.7, active: false },
    { x: 320, y: 175, name: 'Nina C.',   rate: '$35', rating: 4.5, active: false },
  ];
  const cards = [
    { name: 'Marcus R.',  role: 'Master Plumber',   rating: 4.9, reviews: 143, rate: '$45/hr', dist: '0.8km', avail: 'Available now' },
    { name: 'Leila F.',   role: 'Certified Plumber', rating: 4.8, reviews: 87,  rate: '$52/hr', dist: '1.1km', avail: 'In 2 hrs' },
    { name: 'Jake B.',    role: 'Plumber',           rating: 4.6, reviews: 62,  rate: '$38/hr', dist: '1.4km', avail: 'Tomorrow' },
  ];
  return (
    <FXScreen>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        {/* Header */}
        <div style={{ padding: '4px 20px 10px', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            {FXIcons.back(FX.text)}
          </div>
          <div style={{ flex: 1, background: '#fff', borderRadius: 12, display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', boxShadow: '0 1px 6px rgba(0,0,0,0.06)' }}>
            {FXIcons.search(FX.mutedSoft)}
            <span style={{ fontSize: 14, color: FX.text }}>Plumbers near you</span>
          </div>
          <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.accentSoft, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            {FXIcons.filter(FX.accent)}
          </div>
        </div>

        {/* Filter chips */}
        <div style={{ display: 'flex', gap: 8, padding: '0 20px 12px', overflowX: 'auto' }}>
          {['All', 'Top Rated', 'Available', 'Budget'].map((c, i) => (
            <FXChip key={c} label={c} active={i === 0} small />
          ))}
        </div>

        {/* Map */}
        <div style={{ margin: '0 20px', borderRadius: 16, overflow: 'hidden', flexShrink: 0 }}>
          <svg viewBox="0 0 362 230" style={{ width: '100%', display: 'block' }}>
            {/* base */}
            <rect width="362" height="230" fill="#E8E4DD" />
            {/* park */}
            <rect x="10" y="10" width="70" height="80" rx="6" fill="#C9DDAA" />
            <rect x="230" y="120" width="50" height="60" rx="4" fill="#C9DDAA" />
            {/* main roads */}
            <rect y="110" width="362" height="14" fill="#fff" opacity="0.85" />
            <rect x="160" y="0" width="12" height="230" fill="#fff" opacity="0.85" />
            {/* secondary roads */}
            <rect y="50" width="362" height="7" fill="#fff" opacity="0.55" />
            <rect y="175" width="362" height="7" fill="#fff" opacity="0.55" />
            <rect x="80" y="0" width="7" height="230" fill="#fff" opacity="0.55" />
            <rect x="270" y="0" width="7" height="230" fill="#fff" opacity="0.55" />
            {/* blocks */}
            {[[10,60,60,42],[90,10,60,36],[200,10,55,36],[10,125,60,44],[90,125,60,40],[200,130,60,40],[10,180,60,38],[230,185,30,38]].map(([x,y,w,h],i) => (
              <rect key={i} x={x} y={y} width={w} height={h} rx="3" fill="#D5D0C8" />
            ))}
            {/* user location */}
            <circle cx="181" cy="115" r="8" fill="#3B82F6" opacity="0.2" />
            <circle cx="181" cy="115" r="5" fill="#3B82F6" />
            <circle cx="181" cy="115" r="2.5" fill="#fff" />
            {/* provider pins */}
            {pins.map((p, i) => (
              <g key={i}>
                {p.active && <circle cx={p.x} cy={p.y} r="18" fill={FX.accent} opacity="0.15" />}
                <g transform={`translate(${p.x - 12}, ${p.y - 24})`}>
                  <path d="M12 0C7.6 0 4 3.6 4 8c0 5.3 8 16 8 16s8-10.7 8-16c0-4.4-3.6-8-8-8z" fill={p.active ? FX.accent : '#fff'} stroke={p.active ? FX.accentDark : '#ccc'} strokeWidth="1" />
                  <circle cx="12" cy="8" r="4" fill={p.active ? '#fff' : FX.accent} />
                </g>
                {p.active && (
                  <rect x={p.x - 24} y={p.y - 48} width="48" height="18" rx="9" fill="#fff" filter="url(#ds)" />
                )}
                {p.active && (
                  <text x={p.x} y={p.y - 34} textAnchor="middle" fill={FX.text} fontSize="9" fontWeight="700" fontFamily="system-ui">{p.rate}/hr</text>
                )}
              </g>
            ))}
            <defs>
              <filter id="ds" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="1" stdDeviation="2" floodOpacity="0.12" />
              </filter>
            </defs>
          </svg>
        </div>

        {/* Provider cards */}
        <div style={{ padding: '10px 20px 0', fontSize: 14, fontWeight: 700, color: FX.text }}>
          {cards.length} Plumbers Found
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '8px 20px 0', overflow: 'hidden' }}>
          {cards.slice(0, 2).map((p, i) => (
            <FXCard key={p.name} style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
              <FXAvatar size={46} name={p.name} bg={i === 0 ? FX.accentSoft : FX.borderSoft} color={i === 0 ? FX.accent : FX.muted} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div style={{ fontWeight: 600, fontSize: 14 }}>{p.name}</div>
                  <div style={{ fontSize: 14, fontWeight: 700, color: FX.accent }}>{p.rate}</div>
                </div>
                <div style={{ fontSize: 12, color: FX.muted, marginTop: 1 }}>{p.role}</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 5 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    {FXIcons.star('#FBBF24')}
                    <span style={{ fontSize: 12, fontWeight: 600 }}>{p.rating}</span>
                    <span style={{ fontSize: 11, color: FX.muted }}>({p.reviews})</span>
                  </div>
                  <span style={{ fontSize: 11, color: FX.muted }}>• {p.dist}</span>
                  <FXBadge label={p.avail} color={i === 0 ? FX.success : FX.warn} bg={i === 0 ? FX.successSoft : FX.warnSoft} size={10} />
                </div>
              </div>
            </FXCard>
          ))}
        </div>
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={1} />
    </FXScreen>
  );
}

// ─── C4: Provider Detail ─────────────────────────────────────
function C4_ProviderDetail() {
  const services = ['Pipe Repair', 'Drain Cleaning', 'Leak Detection', 'Installation'];
  return (
    <FXScreen bg="#fff">
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {/* Banner */}
        <div style={{ position: 'relative', flexShrink: 0 }}>
          <FXImgBlock width="100%" height={140} label="cover photo" radius={0} />
          <div style={{ position: 'absolute', top: 10, left: 14 }}>
            <div style={{ width: 32, height: 32, borderRadius: '50%', background: 'rgba(255,255,255,0.9)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              {FXIcons.back('#111')}
            </div>
          </div>
          {/* Avatar overlap */}
          <div style={{ position: 'absolute', bottom: -30, left: 20 }}>
            <FXAvatar size={64} name="Marcus R" style={{ border: '3px solid #fff', boxShadow: '0 2px 8px rgba(0,0,0,0.12)' }} />
          </div>
        </div>

        {/* Profile info */}
        <div style={{ padding: '36px 20px 14px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <span style={{ fontSize: 20, fontWeight: 800 }}>Marcus Rivera</span>
                <div style={{ background: FX.successSoft, borderRadius: '50%', width: 18, height: 18, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  {FXIcons.check(FX.success)}
                </div>
              </div>
              <div style={{ fontSize: 13, color: FX.muted, marginTop: 2 }}>Master Plumber • 8 yrs exp.</div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <div style={{ fontSize: 22, fontWeight: 800, color: FX.accent }}>$45</div>
              <div style={{ fontSize: 12, color: FX.muted }}>/hour</div>
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 10 }}>
            <FXStars rating={4.9} size={14} />
            <span style={{ fontSize: 13, fontWeight: 600 }}>4.9</span>
            <span style={{ fontSize: 13, color: FX.muted }}>(143 reviews)</span>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 7 }}>
            {FXIcons.location(FX.muted)}
            <span style={{ fontSize: 13, color: FX.muted }}>0.8 km away • Greenfield District</span>
          </div>
        </div>

        <FXDivider mx={20} />

        {/* Services */}
        <div style={{ padding: '12px 20px' }}>
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 8 }}>Services Offered</div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
            {services.map(s => <FXChip key={s} label={s} small />)}
          </div>
        </div>

        {/* Reviews */}
        <div style={{ padding: '0 20px 10px' }}>
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10 }}>Recent Reviews</div>
          {[
            { name: 'Sarah M.', text: 'Fixed our burst pipe in under an hour. Incredibly professional!', rating: 5, time: '2d ago' },
            { name: 'James L.', text: 'Great work, fair price. Will definitely book again.', rating: 5, time: '1w ago' },
          ].map(r => (
            <div key={r.name} style={{ marginBottom: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 5 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <FXAvatar size={28} name={r.name} bg={FX.borderSoft} color={FX.muted} />
                  <span style={{ fontSize: 13, fontWeight: 600 }}>{r.name}</span>
                </div>
                <span style={{ fontSize: 11, color: FX.muted }}>{r.time}</span>
              </div>
              <FXStars rating={r.rating} size={11} />
              <div style={{ fontSize: 12, color: FX.muted, marginTop: 4, lineHeight: 1.5 }}>{r.text}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Sticky Book button */}
      <div style={{ padding: '12px 20px', borderTop: `1px solid ${FX.border}`, background: '#fff', flexShrink: 0 }}>
        <FXPrimaryBtn label="Book Now — $45/hr" />
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={1} />
    </FXScreen>
  );
}

// ─── C5: Booking Form ────────────────────────────────────────
function C5_BookingForm() {
  const times = ['9:00 AM', '10:00 AM', '11:00 AM', '2:00 PM', '3:00 PM', '4:00 PM'];
  const dates = [
    { day: 'Mon', date: '9', active: false },
    { day: 'Tue', date: '10', active: false },
    { day: 'Wed', date: '11', active: true },
    { day: 'Thu', date: '12', active: false },
    { day: 'Fri', date: '13', active: false },
  ];
  return (
    <FXScreen>
      <FXScreenHeader title="Book Service" back={true} />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 20px' }}>
          {/* Provider mini-card */}
          <FXCard style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
            <FXAvatar size={46} name="Marcus R" />
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 600, fontSize: 14 }}>Marcus Rivera</div>
              <div style={{ fontSize: 12, color: FX.muted }}>Master Plumber</div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <div style={{ fontSize: 16, fontWeight: 700, color: FX.accent }}>$45/hr</div>
              <FXStars rating={4.9} size={10} />
            </div>
          </FXCard>

          {/* Date picker */}
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10 }}>Select Date</div>
          <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
            {dates.map(d => (
              <div key={d.date} style={{
                flex: 1, padding: '10px 4px', borderRadius: 12, textAlign: 'center',
                background: d.active ? FX.accent : '#fff',
                color: d.active ? '#fff' : FX.text,
                boxShadow: d.active ? 'none' : '0 1px 3px rgba(0,0,0,0.06)',
                border: `1.5px solid ${d.active ? FX.accent : FX.border}`,
              }}>
                <div style={{ fontSize: 10, fontWeight: 500, opacity: d.active ? 0.8 : undefined, color: d.active ? '#fff' : FX.muted }}>{d.day}</div>
                <div style={{ fontSize: 17, fontWeight: 700 }}>{d.date}</div>
              </div>
            ))}
          </div>

          {/* Time slots */}
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10 }}>Select Time</div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8, marginBottom: 20 }}>
            {times.map((t, i) => (
              <div key={t} style={{
                padding: '10px 4px', borderRadius: 10, textAlign: 'center',
                fontSize: 13, fontWeight: 500,
                background: i === 2 ? FX.accent : '#fff',
                color: i === 2 ? '#fff' : FX.text,
                border: `1.5px solid ${i === 2 ? FX.accent : FX.border}`,
              }}>{t}</div>
            ))}
          </div>

          {/* Address */}
          <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 8 }}>Service Address</div>
          <FXInput placeholder="Enter your address" value="14 Maple Street, Apt 3" icon={FXIcons.location(FX.mutedSoft)} style={{ marginBottom: 12 }} />
          <FXInput placeholder="Add special instructions..." icon={
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          } style={{ marginBottom: 20 }} />

          {/* Price estimate */}
          <FXCard style={{ background: FX.accentSoft, marginBottom: 16 }} pad={14}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <span style={{ fontSize: 13, color: FX.muted }}>Service fee (est. 2 hrs)</span>
              <span style={{ fontSize: 13, fontWeight: 600 }}>$90.00</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <span style={{ fontSize: 13, color: FX.muted }}>Platform fee</span>
              <span style={{ fontSize: 13, fontWeight: 600 }}>$5.00</span>
            </div>
            <div style={{ height: 1, background: 'rgba(255,102,53,0.2)', margin: '8px 0' }} />
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontSize: 14, fontWeight: 700, color: FX.text }}>Estimated Total</span>
              <span style={{ fontSize: 16, fontWeight: 800, color: FX.accent }}>$95.00</span>
            </div>
          </FXCard>
        </div>

        <div style={{ padding: '12px 20px', borderTop: `1px solid ${FX.border}`, background: FX.surface, flexShrink: 0 }}>
          <FXPrimaryBtn label="Confirm Booking" />
        </div>
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={2} />
    </FXScreen>
  );
}

// ─── C6: Job Status Tracker ───────────────────────────────────
function C6_JobTracker() {
  const steps = [
    { label: 'Requested',   sub: 'Jun 11, 9:00 AM',  done: true,  active: false },
    { label: 'Accepted',    sub: 'Jun 11, 9:14 AM',  done: true,  active: false },
    { label: 'In Progress', sub: 'Today, 2:00 PM',   done: false, active: true  },
    { label: 'Completed',   sub: 'Pending',           done: false, active: false },
    { label: 'Reviewed',    sub: 'Pending',           done: false, active: false },
  ];
  return (
    <FXScreen>
      <FXScreenHeader title="Job Tracker" subtitle="#FixIt-2847" back={true} />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column', padding: '0 20px' }}>
        {/* Provider card */}
        <FXCard style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
          <FXAvatar size={46} name="Marcus R" />
          <div style={{ flex: 1 }}>
            <div style={{ fontWeight: 600 }}>Marcus Rivera</div>
            <div style={{ fontSize: 12, color: FX.muted }}>Pipe Repair • Jun 11</div>
          </div>
          <FXBadge label="In Progress" color={FX.blue} bg={FX.blueSoft} />
        </FXCard>

        {/* Timeline */}
        <div style={{ flex: 1, position: 'relative' }}>
          {steps.map((step, i) => (
            <div key={step.label} style={{ display: 'flex', gap: 14, marginBottom: 4 }}>
              {/* Icon + line */}
              <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 32 }}>
                <div style={{
                  width: 32, height: 32, borderRadius: '50%', flexShrink: 0,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  background: step.done ? FX.success : step.active ? FX.accent : FX.borderSoft,
                  border: step.active ? `2px solid ${FX.accent}` : 'none',
                  boxShadow: step.active ? `0 0 0 4px ${FX.accentSoft}` : 'none',
                }}>
                  {step.done ? FXIcons.check('#fff') : step.active ? (
                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#fff' }} />
                  ) : (
                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: FX.border }} />
                  )}
                </div>
                {i < steps.length - 1 && (
                  <div style={{ width: 2, flex: 1, minHeight: 24, background: step.done ? FX.success : FX.border, marginTop: 2 }} />
                )}
              </div>
              {/* Text */}
              <div style={{ paddingBottom: i < steps.length - 1 ? 20 : 0, paddingTop: 4 }}>
                <div style={{ fontSize: 14, fontWeight: step.active ? 700 : step.done ? 600 : 500, color: step.done || step.active ? FX.text : FX.mutedSoft }}>{step.label}</div>
                <div style={{ fontSize: 12, color: FX.muted, marginTop: 2 }}>{step.sub}</div>
                {step.active && (
                  <div style={{ marginTop: 6, background: FX.accentSoft, borderRadius: 8, padding: '6px 10px', display: 'inline-block' }}>
                    <span style={{ fontSize: 12, color: FX.accent, fontWeight: 600 }}>Marcus is on the way</span>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* Actions */}
        <div style={{ display: 'flex', gap: 10, paddingBottom: 8 }}>
          <FXOutlineBtn label="📞 Call" style={{ flex: 1 }} />
          <FXPrimaryBtn label="💬 Chat" style={{ flex: 1 }} />
        </div>
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={2} />
    </FXScreen>
  );
}

// ─── C7: Rate & Review ───────────────────────────────────────
function C7_Review() {
  const tags = ['On Time', 'Professional', 'Quality Work', 'Great Value', 'Friendly', 'Clean'];
  return (
    <FXScreen bg="#fff">
      <FXScreenHeader title="Rate Experience" back={true} />
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column', padding: '0 20px' }}>
        {/* Provider */}
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', paddingBottom: 20 }}>
          <FXAvatar size={64} name="Marcus R" style={{ marginBottom: 10 }} />
          <div style={{ fontSize: 18, fontWeight: 700 }}>Marcus Rivera</div>
          <div style={{ fontSize: 13, color: FX.muted }}>Pipe Repair • Jun 11, 2:00 PM</div>
        </div>

        <div style={{ fontSize: 15, fontWeight: 600, textAlign: 'center', marginBottom: 14, color: FX.muted }}>How was Marcus's service?</div>

        {/* Stars */}
        <div style={{ display: 'flex', justifyContent: 'center', gap: 10, marginBottom: 24 }}>
          {[1, 2, 3, 4, 5].map(i => (
            <svg key={i} width="38" height="38" viewBox="0 0 24 24">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                fill={i <= 4 ? '#FBBF24' : '#E5E7EB'} />
            </svg>
          ))}
        </div>

        {/* Tag chips */}
        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 10 }}>What stood out?</div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 20 }}>
          {tags.map((t, i) => (
            <FXChip key={t} label={t} active={[0, 1, 2].includes(i)} small />
          ))}
        </div>

        {/* Text area */}
        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 8 }}>Write a Review</div>
        <div style={{ background: FX.borderSoft, borderRadius: 12, padding: '12px 14px', marginBottom: 20, minHeight: 80, fontSize: 14, color: FX.muted, lineHeight: 1.5 }}>
          Marcus was incredibly professional and fixed the leak quickly. Highly recommended!
        </div>

        <FXPrimaryBtn label="Submit Review" />
        <div style={{ textAlign: 'center', marginTop: 12 }}>
          <span style={{ fontSize: 13, color: FX.muted }}>Skip for now</span>
        </div>
      </div>
      <FXBottomNav items={CUSTOMER_NAV} active={2} />
    </FXScreen>
  );
}

Object.assign(window, { C1_Login, C2_Browse, C3_Search, C4_ProviderDetail, C5_BookingForm, C6_JobTracker, C7_Review });
