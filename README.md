# fajtechWEBSITE

The fajtech.ca website: **Astro + Tailwind CSS**, built to a static site and hosted on Hostinger.

## Quick start

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # outputs the site to dist/
```

## Where to change things

| What | File |
|---|---|
| Phone, email, address, hours, social links | `src/config/site.ts` |
| Google Ads tag and conversion labels | `src/config/site.ts` → `googleAds` |
| Zoho SalesIQ chat, Zoho Bookings link | `src/config/site.ts` → `zoho` |
| Services, industries, FAQ text | `src/data/services.ts` |
| Pages | `src/pages/` |
| Colours and fonts | `src/styles/global.css` |

## Google Ads

- The tag `AW-18491599744` (the one from the old site) loads on every page.
- Conversions are sent for **contact form submissions**, **phone clicks** and **email clicks** once you paste their labels into `googleAds.conversions` (Google Ads → Goals → Conversions → *action* → Tag setup → "Use Google tag" → copy the `send_to` value, e.g. `AW-18491599744/AbCdEf`).
- The Google Ads click ID (`gclid`) is captured and attached to each lead (email and Zoho CRM) for offline-conversion tracking.

## Zoho

- **Zoho CRM (leads):** the contact form posts to `/api/contact.php`, which emails the team and, if configured, creates a Lead through Zoho's Web-to-Lead form. Copy `server/fajtech-config.example.php` to the server as `domains/fajtech.ca/fajtech-config.php` (outside `public_html`) and fill in the Zoho values. **Never commit that file.**
- **Zoho SalesIQ (live chat):** paste the widget code into `zoho.salesIqWidgetCode`.
- **Zoho Bookings:** paste your booking page URL into `zoho.bookingsUrl`. Every "Book a consultation" button then goes there.

## Deploying

Hostinger deploys this repo as a **static** site. It runs `npm run build` and publishes `dist/`. Every push to `main` redeploys.

## Before launch

- [ ] Add the business phone number to `src/config/site.ts`
- [ ] Add real client testimonials (with permission) if desired
- [ ] Create conversion actions in Google Ads and paste their labels
- [ ] Upload `fajtech-config.php` to the server and test the contact form
- [ ] Have the privacy policy reviewed
