# Crontinel Lifetime Deal Strategy

**Date:** 2026-04-10
**Project:** Crontinel app (HarunRRayhan/crontinel-app)

---

## 1. Deal Structure

### Tiers

| Tier | Price | Maps To | Limits |
|---|---|---|---|
| Tier 1 (Solo) | $59 | Pro plan ($19/mo) | 1 app, all Pro features |
| Tier 2 (Pro) | $149 | Pro plan ($19/mo) | 5 apps, all Pro features |
| Tier 3 (Agency) | $299 | Team plan ($49/mo) | 15 apps, all Team features |

### What "Lifetime" Means

Lifetime access to the plan tier purchased, including all features available at the time of purchase plus any features added to that tier in the future. Does not include features exclusive to higher tiers added after purchase. "Lifetime" means the lifetime of the product — standard SaaS LTD terms.

### Code Cap

**250 total codes across all tiers.** Distribution:

- Tier 1: 150 codes
- Tier 2: 75 codes
- Tier 3: 25 codes

This caps total LTD exposure at ~$30,000 revenue while creating urgency. At full sellout:

- Tier 1: 150 × $59 = $8,850
- Tier 2: 75 × $149 = $11,175
- Tier 3: 25 × $299 = $7,475
- **Total: $27,500**

This is enough to fund 6+ months of solo development costs while building a recurring revenue base from users who convert after the deal ends.

---

## 2. Platform Options

### AppSumo

**Pros:** Largest LTD audience (1M+ buyers), built-in review system, handles payments and refunds, significant traffic for launch week.

**Cons:** Takes 70% of revenue for first listing (drops to 60% after), attracts deal-hunters who rarely convert to recurring, support burden from non-technical buyers, 60-day refund window.

**Net revenue at full sellout:** ~$8,250 (30% of $27,500)

**Verdict:** The revenue share is brutal. AppSumo works for products that need awareness above all else. Crontinel targets Laravel developers specifically — AppSumo's audience is mostly non-technical SaaS buyers. Poor fit.

### PitchGround

**Pros:** Better revenue split (70-80% to seller), smaller but more technical audience, less support burden.

**Cons:** Much smaller audience than AppSumo, less brand recognition, slower sales velocity.

**Net revenue at full sellout:** ~$19,250-$22,000 (70-80%)

**Verdict:** Better economics but the audience is still not Laravel-specific. Worth considering as a secondary channel.

### Direct (Self-Hosted LTD)

**Pros:** 100% revenue (minus Stripe fees ~3%), full control over messaging, targets the exact audience (Laravel devs), builds direct relationship, no refund policy you didn't set.

**Cons:** No built-in audience, you drive all traffic yourself, need to build a simple purchase flow.

**Net revenue at full sellout:** ~$26,675 (97% after Stripe)

**Verdict:** Best economics and best audience targeting. The tradeoff is that you need to bring the traffic yourself — but the Reddit posts, HN launch, Dev.to article, and Twitter thread are already planned. A direct LTD page on crontinel.com with Stripe Checkout is the right move.

### Recommendation

**Go direct.** Build a `/lifetime` page on the landing site. Use Stripe Checkout for payment processing. Promote through the existing launch channels (Reddit, HN, Twitter, Dev.to, Product Hunt). If sales are slow after 2 weeks, list remaining codes on PitchGround as a secondary channel.

---

## 3. Launch Timeline

### Pre-Launch (Days 1-3)

1. Build `/lifetime` landing page with tier cards, countdown timer, and code remaining counter
2. Set up Stripe products and prices for each tier
3. Build simple license provisioning: purchase → create account → assign plan tier
4. Add LTD badge to user accounts ("Lifetime Pro" / "Lifetime Team")

### Launch Week (Days 4-10)

1. Announce LTD alongside product launch on all channels
2. Include LTD link in every launch post (Reddit, HN, Twitter, Dev.to)
3. Product Hunt launch should mention the limited-time deal
4. Email list announcement with early-bird framing

### Post-Launch (Days 11-21)

1. Send "X codes remaining" updates to email list and Twitter
2. Close individual tiers as they sell out (creates urgency for remaining tiers)
3. Final 48-hour countdown before deal closes

### Close (Day 21)

LTD page converts to "Deal ended" with email capture for future deals. All future customers go to regular monthly/annual pricing.

**Total LTD window: 3 weeks.** Long enough to capture the launch wave plus stragglers, short enough to maintain urgency.

---

## 4. Conversion Strategy

The goal is not just LTD revenue — it is building a user base that generates word-of-mouth and eventual recurring revenue.

### During the Deal

- Every LTD customer gets onboarding email sequence (3 emails over 7 days)
- Dashboard prominently shows "Lifetime Pro" badge (social proof when they screenshot)
- Encourage reviews and testimonials in exchange for priority feature requests

### After the Deal

- LTD customers who hit tier limits can upgrade to a higher tier at the difference in monthly pricing
- LTD customers become the beta testers for new features (they are invested, they will give feedback)
- Their apps appearing on Crontinel status pages (free tier) create organic backlinks

### Preventing LTD Regret

Common LTD failure mode: seller resents giving away lifetime access for one-time payment. Mitigations:

- The 250 code cap means LTD users are never more than ~10% of total user base at scale
- LTD users validate the product and generate testimonials during the most critical growth phase
- The revenue funds development that would otherwise require personal savings or fundraising
- Features added to higher tiers (Business, Enterprise) are not included in LTD, preserving upgrade revenue

---

## 5. Technical Implementation

### What to Build

1. **Stripe Checkout integration**: 3 one-time products, webhook to provision accounts
2. **LTD license model**: `licenses` table with `type` (ltd_solo, ltd_pro, ltd_agency), `stripe_payment_id`, `redeemed_at`
3. **Landing page**: `/lifetime` route on the Astro landing site with tier cards, Stripe Checkout buttons, remaining code count (pulled from API or hardcoded and manually updated)
4. **Code counter API**: Simple endpoint that returns remaining codes per tier, called by the landing page

### Effort Estimate

| Task | Hours |
|---|---|
| Stripe products + checkout integration | 4 |
| License provisioning webhook + model | 3 |
| Landing page design and build | 4 |
| Code counter + urgency UI | 2 |
| Onboarding email sequence (3 emails) | 2 |
| Testing end-to-end purchase flow | 2 |
| **Total** | **~17 hours** |

---

## 6. Risk Management

| Risk | Mitigation |
|---|---|
| Deal-hunters who never use the product | Cap at 250 codes; focus promotion on Laravel communities where users have real need |
| Support burden from LTD users | Self-serve docs, community Discord, limit support to email (no live chat for LTD) |
| Feature creep from LTD users demanding additions | LTD covers the tier purchased at time of sale; new higher-tier features are not included |
| Regret over giving lifetime access | 250 cap means bounded exposure; revenue funds critical early development |
| Stripe dispute/chargeback | Clear terms on the purchase page; 14-day refund policy (not 60 days like AppSumo) |

---

## Summary

Go direct with Stripe Checkout. Three tiers ($59/$149/$299), 250 total codes, 3-week window aligned with product launch. Skip AppSumo (bad revenue share, wrong audience). Keep PitchGround as a backup channel if direct sales are slow. Total implementation is ~17 hours. Maximum revenue exposure is ~$27,500, which funds 6+ months of development while building a committed early user base.
