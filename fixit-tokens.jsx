// fixit-tokens.jsx — FixIt design tokens + shared micro-components
// Exports everything to window for use by other screen files.

const FX = {
  accent:      '#FF6635',
  accentSoft:  '#FFF2EE',
  accentDark:  '#E04F20',
  bg:          '#F7F8FA',
  surface:     '#FFFFFF',
  text:        '#111827',
  muted:       '#6B7280',
  mutedSoft:   '#9CA3AF',
  border:      '#E5E7EB',
  borderSoft:  '#F3F4F6',
  success:     '#22C55E',
  successSoft: '#F0FDF4',
  warn:        '#F59E0B',
  warnSoft:    '#FFFBEB',
  blue:        '#3B82F6',
  blueSoft:    '#EFF6FF',
  error:       '#EF4444',
  errorSoft:   '#FEF2F2',
  purple:      '#8B5CF6',
  purpleSoft:  '#F5F3FF',
};

// ── SVG Icons ────────────────────────────────────────────────
const FXIcons = {
  home: (c='currentColor') => <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>,
  search: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>,
  calendar: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>,
  user: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>,
  chat: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>,
  bell: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>,
  tool: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>,
  grid: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>,
  upload: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>,
  location: (c='currentColor') => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>,
  check: (c='currentColor') => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>,
  send: (c='currentColor') => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2" fill={c}/></svg>,
  shield: (c='currentColor') => <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>,
  star: (c='#FBBF24') => <svg width="16" height="16" viewBox="0 0 24 24" fill={c} stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>,
  camera: (c='currentColor') => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>,
  back: (c='currentColor') => <svg width="10" height="18" viewBox="0 0 10 18" fill="none" stroke={c} strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 1L1 9l8 8"/></svg>,
  plus: (c='currentColor') => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2.5" strokeLinecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>,
  filter: (c='currentColor') => <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>,
  briefcase: (c='currentColor') => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>,
  x: (c='currentColor') => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2.5" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>,
  edit: (c='currentColor') => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>,
};

// ── Shared UI Primitives ─────────────────────────────────────

function FXScreen({ children, bg = FX.bg, accent }) {
  return (
    <div style={{
      background: bg,
      fontFamily: '"DM Sans", system-ui, sans-serif',
      WebkitFontSmoothing: 'antialiased',
      height: '100%',
      display: 'flex',
      flexDirection: 'column',
      color: FX.text,
      position: 'relative',
    }}>
      <div style={{ height: 62, flexShrink: 0 }} />
      {children}
    </div>
  );
}

function FXScreenHeader({ title, subtitle, right, back = false, accent = FX.accent }) {
  return (
    <div style={{ padding: '10px 20px 12px', flexShrink: 0, background: 'inherit' }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          {back && (
            <div style={{ width: 34, height: 34, borderRadius: '50%', background: FX.borderSoft, display: 'flex', alignItems: 'center', justifyContent: 'center', marginRight: 2 }}>
              {FXIcons.back(FX.text)}
            </div>
          )}
          <div>
            <div style={{ fontSize: 20, fontWeight: 700, color: FX.text, lineHeight: 1.2 }}>{title}</div>
            {subtitle && <div style={{ fontSize: 13, color: FX.muted, marginTop: 2 }}>{subtitle}</div>}
          </div>
        </div>
        {right && <div>{right}</div>}
      </div>
    </div>
  );
}

function FXCard({ children, style = {}, onClick, pad = 14 }) {
  return (
    <div onClick={onClick} style={{
      background: FX.surface, borderRadius: 16,
      boxShadow: '0 1px 3px rgba(0,0,0,0.06), 0 2px 8px rgba(0,0,0,0.04)',
      padding: pad, cursor: onClick ? 'pointer' : 'default',
      ...style,
    }}>{children}</div>
  );
}

function FXAvatar({ size = 40, name = '', bg = FX.accentSoft, color = FX.accent, style = {} }) {
  const initials = name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() || '•';
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', background: bg, color,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      fontSize: size * 0.36, fontWeight: 700, flexShrink: 0, ...style,
    }}>{initials}</div>
  );
}

function FXBadge({ label, color = FX.accent, bg = FX.accentSoft, size = 11 }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', padding: '3px 9px',
      borderRadius: 100, fontSize: size, fontWeight: 600, color, background: bg,
    }}>{label}</span>
  );
}

function FXStars({ rating = 4.5, size = 13 }) {
  return (
    <div style={{ display: 'flex', gap: 2 }}>
      {[1, 2, 3, 4, 5].map(i => (
        <svg key={i} width={size} height={size} viewBox="0 0 24 24">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
            fill={i <= Math.round(rating) ? '#FBBF24' : '#E5E7EB'} />
        </svg>
      ))}
    </div>
  );
}

function FXChip({ label, active = false, onClick, small = false }) {
  return (
    <span onClick={onClick} style={{
      display: 'inline-flex', alignItems: 'center', whiteSpace: 'nowrap',
      padding: small ? '4px 10px' : '7px 14px',
      borderRadius: 100, fontSize: small ? 12 : 13, fontWeight: 500,
      background: active ? FX.accent : FX.surface,
      color: active ? '#fff' : FX.text,
      border: `1.5px solid ${active ? FX.accent : FX.border}`,
      cursor: 'pointer',
    }}>{label}</span>
  );
}

function FXPrimaryBtn({ label, icon, style = {}, disabled = false, accent = FX.accent }) {
  return (
    <div style={{
      background: disabled ? FX.border : accent,
      color: disabled ? FX.muted : '#fff',
      borderRadius: 14, padding: '15px 20px',
      fontSize: 15, fontWeight: 600, textAlign: 'center',
      display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
      cursor: disabled ? 'default' : 'pointer', letterSpacing: 0.1,
      ...style,
    }}>
      {icon && icon}
      {label}
    </div>
  );
}

function FXOutlineBtn({ label, style = {}, accent = FX.accent }) {
  return (
    <div style={{
      background: 'transparent', color: accent,
      border: `1.5px solid ${accent}`, borderRadius: 14,
      padding: '13px 20px', fontSize: 15, fontWeight: 600,
      textAlign: 'center', cursor: 'pointer', ...style,
    }}>{label}</div>
  );
}

function FXInput({ placeholder, value, icon, type = 'text', style = {} }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: 10,
      background: FX.borderSoft, borderRadius: 12,
      padding: '13px 15px', ...style,
    }}>
      {icon && <span style={{ color: FX.mutedSoft, display: 'flex' }}>{icon}</span>}
      <span style={{ fontSize: 15, color: value ? FX.text : FX.mutedSoft, flex: 1 }}>
        {value || placeholder}
      </span>
    </div>
  );
}

function FXDivider({ mx = 16 }) {
  return <div style={{ height: 1, background: FX.border, margin: `0 ${mx}px` }} />;
}

function FXBottomNav({ items, active = 0 }) {
  return (
    <div style={{
      background: 'rgba(255,255,255,0.97)',
      backdropFilter: 'blur(12px)',
      borderTop: `1px solid ${FX.border}`,
      display: 'flex', paddingBottom: 22, paddingTop: 10,
      flexShrink: 0,
    }}>
      {items.map((item, i) => (
        <div key={i} style={{
          flex: 1, display: 'flex', flexDirection: 'column',
          alignItems: 'center', gap: 3,
          color: i === active ? FX.accent : FX.mutedSoft,
        }}>
          <div style={{ display: 'flex' }}>{item.icon(i === active ? FX.accent : FX.mutedSoft)}</div>
          <div style={{ fontSize: 10, fontWeight: i === active ? 600 : 400 }}>{item.label}</div>
          {i === active && <div style={{ width: 4, height: 4, borderRadius: '50%', background: FX.accent, marginTop: -1 }} />}
        </div>
      ))}
    </div>
  );
}

function FXImgBlock({ width, height, label = 'image', radius = 12, style = {} }) {
  return (
    <div style={{
      width, height, borderRadius: radius,
      background: 'repeating-linear-gradient(45deg, #F0F1F3 0px, #F0F1F3 6px, #E8E9EC 6px, #E8E9EC 12px)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      flexShrink: 0, overflow: 'hidden', ...style,
    }}>
      <span style={{ fontSize: 11, color: FX.mutedSoft, fontFamily: 'monospace', textAlign: 'center', padding: '0 8px' }}>{label}</span>
    </div>
  );
}

const CUSTOMER_NAV = [
  { icon: FXIcons.home,   label: 'Home' },
  { icon: FXIcons.search, label: 'Explore' },
  { icon: FXIcons.calendar, label: 'Bookings' },
  { icon: FXIcons.user,   label: 'Profile' },
];

const PROVIDER_NAV = [
  { icon: FXIcons.grid,      label: 'Dashboard' },
  { icon: FXIcons.bell,      label: 'Requests' },
  { icon: FXIcons.briefcase, label: 'Jobs' },
  { icon: FXIcons.chat,      label: 'Chat' },
  { icon: FXIcons.user,      label: 'Profile' },
];

const ADMIN_NAV = [
  { icon: FXIcons.grid,     label: 'Overview' },
  { icon: FXIcons.shield,   label: 'Verify' },
  { icon: FXIcons.user,     label: 'Users' },
  { icon: FXIcons.calendar, label: 'Bookings' },
];

Object.assign(window, {
  FX, FXIcons,
  FXScreen, FXScreenHeader, FXCard, FXAvatar, FXBadge, FXStars,
  FXChip, FXPrimaryBtn, FXOutlineBtn, FXInput, FXDivider,
  FXBottomNav, FXImgBlock,
  CUSTOMER_NAV, PROVIDER_NAV, ADMIN_NAV,
});
