// ─────────────────────────────────────────────────────────────
//  FAJTech site settings — edit this file to update business info,
//  Google Ads tracking and Zoho integrations across the whole site.
//  Nothing in here is secret: it all ends up in the public HTML.
//  Server-side secrets (Zoho tokens, etc.) live on the server in
//  fajtech-config.php, OUTSIDE public_html — see README.md.
// ─────────────────────────────────────────────────────────────

export const site = {
  name: 'FAJTech',
  legalName: 'FAJTech',
  tagline: 'Managed IT & Cybersecurity for Canadian Businesses',
  url: 'https://fajtech.ca',
  email: 'info@fajtech.ca',
  /** Shown in header/footer/contact when set, e.g. '+1 (403) 555-0123'. Leave '' to hide. */
  phone: '+1 (403) 926-4494',
  /** Phone line hours, Calgary time (calls go to the Canadian number only) */
  dayShift: '8:00 am – 7:00 pm',
  /** Support WhatsApp (Cairo team) — messages and WhatsApp calls 24/7; no regular phone calls */
  afterHours: {
    label: '24/7',
    phone: '+20 122 370 4138',
    whatsapp: 'https://wa.me/201223704138?text=' + encodeURIComponent('Hi FAJTech, I need IT support. My name and company: '),
  },
  address: {
    street: '415 Pinewind Rd NE',
    city: 'Calgary',
    region: 'AB',
    regionName: 'Alberta',
    postalCode: 'T1Y 2M8',
    country: 'CA',
  },
  /** Show the street address publicly? (false = only "Calgary, Alberta") */
  showStreetAddress: true,
  /** Support availability (shown everywhere) */
  support: '24/7/365 support',
  /** Office / sales hours */
  hours: 'Phone: 8:00 am – 7:00 pm MT · WhatsApp 24/7',
  social: {
    linkedin: '',
    facebook: '',
    x: '',
  },
};

// ── Google Ads ────────────────────────────────────────────────
// The same Google Ads tag that is on the current fajtech.ca, so
// existing campaigns and audiences keep working.
export const googleAds = {
  id: 'AW-18491599744',
  /**
   * Conversion labels from Google Ads → Goals → Conversions → (action) → Tag setup.
   * Format: 'AW-18491599744/AbCdEfGhIj'. Leave '' until created.
   */
  conversions: {
    /** Contact form sent (/thank-you/?source=contact). */
    contactForm: 'AW-18491599744/wp3uCPrx8bodEIDXvfFE',
    /** Free IT assessment requested (/thank-you/?source=assessment). Falls back to contactForm if empty. */
    assessment: 'AW-18491599744/VcJGCJru8bodEIDXvfFE',
    /** Consultation booked or requested (/thank-you/?source=booking or booking-request). */
    booking: 'AW-18491599744/_v3FCK3y8bodEIDXvfFE',
    /** Live chat started (visitor sent their first question). */
    chatStart: '',
    /** Click on the phone number. */
    phoneClick: 'AW-18491599744/sr_bCKv58bodEIDXvfFE',
    /** Click on the email address (usually a secondary conversion). */
    emailClick: '',
  },
  /** Optional Google Analytics 4 ID, e.g. 'G-XXXXXXXXXX'. */
  ga4Id: '',
};

// ── Visitor insights ─────────────────────────────────────────
export const insights = {
  /**
   * Microsoft Clarity project ID (clarity.microsoft.com → your project → Settings → Overview,
   * a short code like 'k3x9abcd12'). Leave '' to turn Clarity off.
   */
  clarityId: '',
  /**
   * Google Search Console HTML-tag verification code (only needed if you verify with the
   * "HTML tag" method; the DNS method needs nothing here). Just the content="..." value.
   */
  googleSiteVerification: '',
};

// ── Zoho ──────────────────────────────────────────────────────
export const zoho = {
  /**
   * Zoho SalesIQ live chat. Paste ONLY the widget code value from
   * SalesIQ → Settings → Brands → Installation (the long string after `widgetcode:`).
   * Leave '' to disable chat.
   */
  salesIqWidgetCode: '',
  /** Zoho data centre for SalesIQ: 'com', 'eu', 'in', 'com.au', 'ca', 'jp' */
  salesIqRegion: 'ca',
  /**
   * Zoho Bookings page embedded on /book-a-consultation/, e.g.
   * 'https://fajtech.zohobookings.ca/portal-embed#/consultation'
   * (Zoho Bookings → Share → Embed → copy the iframe's src).
   * Leave '' and the page shows a "request a time" form instead.
   */
  bookingsUrl: '', // replaced by the built-in booking calendar (/api/booking.php + Exchange)
};
