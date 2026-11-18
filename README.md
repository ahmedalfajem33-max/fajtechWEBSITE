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
- **Campaign management, budget rules and plan: see [`docs/google-ads-plan.md`](docs/google-ads-plan.md).**
- Conversions are sent for the **free assessment**, **consultation booking**, **contact form**, **chat started**, **phone clicks** and **email clicks** (with enhanced conversions) once you paste their labels into `googleAds.conversions` (Google Ads → Goals → Conversions → *action* → Tag setup → "Use Google tag" → copy the `send_to` value, e.g. `AW-18491599744/AbCdEf`).
- The Google Ads click ID (`gclid`) is captured and attached to each lead (email and Zoho CRM) for offline-conversion tracking.

## Zoho

- **Zoho CRM (leads):** the contact form posts to `/api/contact.php`, which emails the team and, if configured, creates a Lead through Zoho's Web-to-Lead form. Copy `server/fajtech-config.example.php` to the server as `domains/fajtech.ca/fajtech-config.php` (outside `public_html`) and fill in the Zoho values. **Never commit that file.**
- **Zoho SalesIQ (live chat):** paste the widget code into `zoho.salesIqWidgetCode`.
- **Zoho Bookings (Book a consultation):** every "Book" button goes to `/book-a-consultation/`. Until `zoho.bookingsUrl` is set, that page shows a "request a time" form that emails the team. Once set, it embeds the live Zoho Bookings calendar instead. In Zoho Bookings, set the redirect after booking to `https://fajtech.ca/thank-you/?source=booking` so each booking counts as a Google Ads conversion (`googleAds.conversions.booking`).

## Live chat

The site has its own private live chat (no Zoho, no WhatsApp, no third party): the chat bubble on every page, and the team inbox at **https://fajtech.ca/agent/** (not linked anywhere, not indexed).

- **Visitor side:** `src/components/ChatWidget.astro`. It asks for name, email and the question, then shows the conversation. Any element with `data-open-chat` opens it. If a visitor leaves before you answer, your reply is emailed to them with a link back into the chat.
- **Team inbox:** `src/pages/agent/index.astro`. Sign in, see open/closed chats, reply, close. Click "Turn on alerts" for a sound and browser notifications. On a phone, open the link and "Add to Home Screen".
- **Team members:** admins add people under the ☰ menu → Team members (with a temporary password; they choose their own at first sign-in).
- **Server:** `public/api/chat.php`, MySQL database `u954394309_fajchat` on Hostinger. The database login lives in `domains/fajtech.ca/fajtech-chat.php`, **outside** `public_html`. Never commit it. Tables are created automatically.
- **Alerts when a chat starts:**
  - **Email** to `mail_to` in `fajtech-config.php` (default info@fajtech.ca) **and** every team member who has "Email me when a new chat starts" on (inbox → ☰ → Settings & notifications).
  - **Push notifications** on each device where someone tapped "Turn on notifications" in the inbox. On iPhone: open fajtech.ca/agent/ in Safari → Share → Add to Home Screen → open the app → Turn on notifications.
  - **Telegram**: an admin connects a bot under Settings & notifications (steps are shown there). The bot token is stored in the chat database, never in this repository.
  - **Reply from Telegram**: each member links their Telegram once (Settings & notifications → Link my Telegram → send `/link CODE` in the group). Then replying to a chat alert in Telegram sends the answer to the visitor under that member's chat name; the bot reacts 👍 when delivered. While a chat is being answered from Telegram, the visitor's next messages are posted to the group for 30 minutes. Telegram talks to `/api/chat.php?action=tg_webhook`, protected by a secret header.
  - While nobody has the inbox open, further visitor messages also ping push/Telegram (at most every 2 minutes per chat).
- WhatsApp stays only on the Contact page and in the footer.

## Contacts & marketing consent (CASL)

- Everyone who uses the **contact form, free assessment, consultation booking or live chat** is saved in the `contacts` table (same database as the chat), matched by email so repeat visitors update one record.
- If they tick **"Yes, send me occasional IT tips and news from FAJTech…"**, a consent record is stored: date/time, form, page, IP and the exact wording (`contact_consent_log` keeps the history). A later form without the tick does **not** remove consent; only unsubscribing does.
- **Team inbox → ☰ → Contacts** (admins): search, filter (Agreed / Not agreed / Unsubscribed), unsubscribe or delete someone, and **export CSV** for Brevo, Mailchimp or Hostinger Reach. The export includes each person's personal unsubscribe link (`https://fajtech.ca/unsubscribe/?t=…`).
- Only send marketing to people marked **Agreed**. Every marketing email must show FAJTech's name and mailing address and a working unsubscribe link (process unsubscribes within 10 business days). When you start using a newsletter tool, import the CSV and keep both lists in sync (unsubscribes in the tool should also be marked in the inbox, and vice versa).
- Code: `public/api/leads.php` (saving, consent, unsubscribe), actions `contacts`, `contacts_export`, `contact_unsubscribe`, `contact_delete`, `unsubscribe` in `public/api/chat.php`, page `src/pages/unsubscribe.astro`.

## How the website sends email

fajtech.ca mail lives on our own Exchange server (mail.fajtech.ca), which refuses mail from the internet that claims to be from @fajtech.ca. So `public/api/mailer.php` sends **all** website email (team notifications, customer confirmations, chat emails) through Exchange itself (EWS over HTTPS) using the booking mailbox in `fajtech-config.php` (`booking.ews_user` / `ews_pass`). Customer confirmations are shown as from `confirm_from` (info@fajtech.ca) if the booking mailbox has *Send As* permission on it in Exchange, otherwise they come from the booking mailbox. Hostinger's PHP `mail()` is only a fallback (it isn't reliably delivered: not DKIM-signed for fajtech.ca). Set `'mail_via' => 'php'` in `fajtech-config.php` to turn the Exchange route off.

## Confirmation emails

Every form submission (contact, free assessment, request-a-time) sends the visitor a branded confirmation from `info@fajtech.ca` (override with `confirm_from` in `fajtech-config.php`). To prevent abuse, it only repeats a cleaned first name and the chosen day/time, never the visitor's message. Zoho Bookings calendar bookings get Zoho's own confirmation.

## Deploying

Hostinger deploys this repo from GitHub as a Node.js build (app type `astro`, build script `build`, output directory `dist`). It runs `npm run build` and publishes `dist/`. Every push to `main` redeploys.

- Preview: https://new.fajtech.ca
- Live (after launch): https://fajtech.ca

## Before launch

- [ ] Add the business phone number to `src/config/site.ts`
- [ ] Add real client testimonials (with permission) if desired
- [ ] Create conversion actions in Google Ads and paste their labels
- [ ] Upload `fajtech-config.php` to the server and test the contact form
- [ ] Have the privacy policy reviewed
