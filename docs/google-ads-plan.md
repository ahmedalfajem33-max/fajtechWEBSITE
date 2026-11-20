# Google Ads: status and plan (hand-off note)

_Last updated: 2026-11-20. Read this first in any new session that manages FAJTech's Google Ads._

## Ground rules (agreed with the owner)

- Google Ads is managed through the **Windsor.ai** connector (connector `google_ads`, account `761-790-3566`; Windsor.ai **trial** plan since 2026-11-20). Adspirer (free plan, 15 actions/month, used up until 2026-12-02) was the previous connector.
- Windsor.ai asks for a confirmation (preview + token) for anything that starts or increases spend: enabling a campaign/ad group/ad/keyword, budget or bid changes. Show the preview to the owner and only confirm after their OK.
- **Budget: CAD 500 per month, maximum** (about CAD 16/day across all campaigns). Never exceed it.
- **Never launch, enable or raise the budget of a campaign without the owner's explicit OK.** New campaigns are created **paused** and shown to the owner first.
- Send a short weekly report: spend, clicks, conversions (leads), cost per lead, and every change made.
- The owner is in Cairo (Africa/Cairo); the business and ads target **Calgary, Alberta** (America/Edmonton).

## Current state (2026-11-18)

- Account: **FajTech, customer ID 7617903566** (connected through Adspirer; Adspirer is on the **free plan: 15 actions/month**, resets the 2nd of each month, so use calls sparingly).
- **Old campaign** "FajTech | Alberta + Toronto | Search | Leads" (ID 24332411214): **PAUSED** on 2026-11-18. It spent CAD 1,881.99 in 30 days for 62 clicks (about CAD 30/click) and 0 recorded conversions: 20 broad generic keywords, Alberta + Toronto, no negative keywords, Maximize Conversions with no working conversion tracking, CAD 50/day. Keep paused; remove once the new campaign performs.
- **New campaign** "FajTech | Calgary | Search | IT Support + Free Assessment" (ID **24487330043**): created **PAUSED**, CAD 16/day, Calgary + Airdrie + Cochrane + Chestermere + Okotoks, 3 ad groups (Managed IT Support Calgary → /free-it-assessment/, Cybersecurity Calgary → /services/cybersecurity/, Microsoft 365 & Cloud Calgary → /services/cloud-services/), 43 keywords (Adspirer adds them as broad match), 35 negative keywords, 4 sitelinks, Maximize Conversions.
- **Conversion actions created** (2026-11-18): Website - Free assessment request (ID 7908128538, CAD 300, primary), Website - Consultation booked (7908129069, CAD 300, primary), Website - Contact form (7908129018, CAD 100, primary), Website - Chat started (7908129282, CAD 50, secondary), Website - Phone number click (7908129963, CAD 50, secondary). The pre-existing "Calls from ads" action remains.
- **Before launching the new campaign:**
  1. Paste the five `send_to` labels into `src/config/site.ts → googleAds.conversions` (assessment, booking, contactForm, chatStart, phoneClick) and deploy. Adspirer can't read the labels; the owner copies them from Google Ads → Goals → Conversions → action → Tag setup → "Use Google tag" → install yourself → event snippet.
  2. Turn on Enhanced conversions for web (Google tag method) in Google Ads.
  3. Test conversions with Google Tag Assistant.
  4. Check location targeting is "Presence" only and consider a max-CPC safeguard (e.g. switch to Maximize clicks with a CPC cap of about CAD 8 until 15+ conversions), then resume with the owner's OK.

## Current state (2026-11-20) — supersedes the 2026-11-18 notes above

- The owner **deleted all previous campaigns** on 2026-11-19 (the old Alberta + Toronto campaign and the paused Calgary campaign 24487330043 are REMOVED and cannot be restored).
- **New campaign** "FajTech | Calgary | Search | IT Support + Free Assessment" — ID **24521467641**, **PAUSED**, built 2026-11-20 via Windsor.ai:
  - Budget **CAD 16/day**; bidding **Maximize clicks** (target_spend). **CPC cap CAD 8: applied 2026-11-20** with the owner's approval.
  - Location: **40 km radius around downtown Calgary** (51.0447, -114.0719) — covers Airdrie, Cochrane, Chestermere, Okotoks. Location option **PRESENCE** — set by the owner in the UI and verified 2026-11-20.
  - Language: English. Schedule (Calgary time): Mon–Fri 07:00–19:00, Sat 09:00–14:00.
  - Ad groups (all PAUSED, keywords PAUSED, PHRASE + EXACT only):
    - Managed IT Support Calgary — ad group **203286519018**, 24 keywords, 2 RSAs (→ /free-it-assessment/, /services/managed-it/)
    - Cybersecurity Calgary — ad group **200612217965**, 16 keywords, 2 RSAs (→ /services/cybersecurity/, /free-it-assessment/)
    - Microsoft 365 & Cloud Calgary — ad group **200612225645**, 16 keywords, 2 RSAs (→ /services/cloud-services/, /services/backup-disaster-recovery/)
  - 37 campaign-level negative keywords (jobs, courses, home, DIY, repair, etc.). "free" is deliberately NOT negative (the offer is a free assessment).
  - Ads use final URL suffix `utm_source=google&utm_medium=cpc&utm_campaign=calgary_search`.
  - **Not created (Windsor.ai has no action for them):** sitelinks, callouts, call asset. Add in the Google Ads UI (Assets) or later.
- **Conversion labels: DONE 2026-11-20** — all five `send_to` labels are in `site.ts` and live (assessment VcJGCJru8bodEIDXvfFE, booking _v3FCK3y8bodEIDXvfFE, contactForm wp3uCPrx8bodEIDXvfFE, chatStart Tle0CIL08bodEIDXvfFE, phoneClick sr_bCKv58bodEIDXvfFE), each tested.
- **To launch (each step needs the owner's OK):** ~~enhanced conversions on~~ (verified); ~~Presence setting~~ (verified); ~~apply CPC cap~~ (done); test with Tag Assistant (optional); then enable campaign → ad groups → ads → keywords.

## Account & tracking status

- Google Ads tag on every page: **AW-18491599744** (`src/config/site.ts` → `googleAds.id`).
- Conversion events the website fires (`window.fajTrack`, `src/layouts/Base.astro`):

  | Key in `site.ts` | When it fires | Suggested conversion action | Value | Primary? |
  |---|---|---|---|---|
  | `assessment` | `/thank-you/?source=assessment` | Free assessment request | CAD 300 | Primary |
  | `booking` | `/thank-you/?source=booking` / `booking-request` | Consultation booked | CAD 300 | Primary |
  | `contactForm` | `/thank-you/?source=contact` | Contact form | CAD 100 | Primary |
  | `chatStart` | visitor sends first chat question | Chat started | CAD 50 | Secondary |
  | `phoneClick` | click on the phone number | Phone click | CAD 50 | Secondary |

- ~~TODO: conversion labels~~ — done 2026-11-20 (see above). Old note: Create the conversion actions (via Adspirer or in the Google Ads UI), paste the `send_to` values into `site.ts`, deploy (push to `main`; Hostinger auto-deploys), and test with Google Tag Assistant.
- **Enhanced conversions** are implemented on the website (email/phone sent to `gtag('set','user_data')` at conversion). Turn on "Enhanced conversions for web → Google tag" in Google Ads.
- The Google Ads click ID (`gclid`, or `gbraid`/`wbraid` on iOS) is saved on **any** landing page and sent with every lead email and booking.
- Tag loading: immediately for visitors arriving from an ad (`gclid`/`gbraid`/`wbraid` in the URL) and on `/thank-you/`; otherwise 1.5 s after page load or on the first tap/click (2026-11-19 website readiness check).

## Campaign plan (draft, to confirm with the owner before launch)

**Campaign 1: Search, "Managed IT & IT support Calgary"** (about CAD 12/day)
- Location: Calgary + 30–40 km (Airdrie, Cochrane, Chestermere, Okotoks). "Presence: people in or regularly in" the area only.
- Schedule: Mon–Fri 7 am–7 pm and Sat 9 am–2 pm Calgary time to start.
- Bidding: start with **Maximize clicks with a max CPC cap (about CAD 8)** or Manual CPC; switch to **Maximize conversions** after about 15–30 conversions.
- Ad groups and keywords (phrase and exact match):
  - Managed IT: "managed it services calgary", "it support calgary", "it services calgary", "outsourced it calgary", "it company calgary"
  - Small business IT: "small business it support calgary", "it support for small business"
  - Cybersecurity: "cybersecurity services calgary", "cyber security company calgary", "network security calgary"
  - Microsoft 365 / cloud: "microsoft 365 support calgary", "office 365 migration calgary"
- Landing pages: `/free-it-assessment/` (main), `/services/managed-it/`, `/services/cybersecurity/`, `/locations/calgary/`.
- Responsive search ads: 3 per ad group. Lead with 24/7/365 support, the free IT assessment, flat monthly pricing and Calgary-based. Use sitelinks (Services, Free assessment, Book a consultation, Contact), callouts (24/7/365 support, Canadian company, no long contracts, if true) and a call asset with the Calgary number +1 (403) 926-4494 (8 am–7 pm MT).

**Campaign 2 (later, about CAD 4/day): Search, "Emergency IT / cybersecurity help"**, e.g. "ransomware help calgary", "computer virus removal business calgary", "email hacked business calgary". Start only once Campaign 1 has data.

**Negative keywords (account level):** jobs, job, career, careers, salary, hiring, course, courses, training, certification, degree, college, free, diy, how to, reddit, youtube, wikipedia, cheap, used, repair shop, phone repair, iphone, android, game, gaming, home, personal, student, internship, resume.

**Not yet:** Display, Performance Max and YouTube. Too broad for CAD 500/month; revisit once Search has steady conversions.

## Weekly routine

1. Check spend vs. the CAD 500/month limit (pace about CAD 115/week).
2. Search terms report → add irrelevant terms as negatives.
3. Pause keywords with spend > CAD 60 and no conversions; check Quality Score of the rest.
4. Report to the owner (spend, leads, cost per lead, changes, next steps).
