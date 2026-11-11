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
  phone: '',
  address: {
    street: '415 Pinewind Rd NE',
    city: 'Calgary',
    region: 'AB',
    regionName: 'Alberta',
    postalCode: '',
    country: 'CA',
  },
  /** Show the street address publicly? (false = only "Calgary, Alberta") */
  showStreetAddress: false,
  /** Support availability (shown everywhere) */
  support: '24/7/365 support',
  /** Office / sales hours */
  hours: 'Office: Mon–Fri, 8:00 am – 6:00 pm MT',
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
    contactForm: '',
    phoneClick: '',
    emailClick: '',
    /** Fired on /thank-you/?source=booking — set that as the redirect URL in Zoho Bookings. */
    booking: '',
  },
  /** Optional Google Analytics 4 ID, e.g. 'G-XXXXXXXXXX'. */
  ga4Id: '',
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
  bookingsUrl: 'https://fajtech.zohobookings.com/portal-embed#/5016282000000039071',
};
