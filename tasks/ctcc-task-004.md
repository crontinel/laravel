# Task: Quick SEO Fixes — Twitter Meta, OG Images, Structured Data, GitHub Org README

## Context
Crontinel is in early launch mode. The SEO audit (landing/SEO/SEO_AUDIT.md) identified specific gaps that need fixing:
1. Missing `twitter:site` meta tag in Base.astro
2. VS pages falling back to default OG image — need per-page OG images
3. Pricing and Features pages may be missing full structured data
4. GitHub org profile README may be missing or outdated

## Tasks

### 1. Add twitter:site meta tag
Edit `landing/src/components/Base.astro` (or wherever Base.astro lives) to add:
```html
<meta name="twitter:site" content="@crontinel">
```
Make sure it's inside the `<head>` tag alongside other Twitter card meta tags.

### 2. Per-page OG images for VS pages
Check `landing/src/pages/vs/` — if there are VS page components that already generate dynamic OG images, make sure they're wired up. If not, the simplest fix is to add a `og:image` frontmatter override to each VS page to use a context-specific image, or create a simple programmatic OG image.

Actually: Check if the site already has an OG image generation mechanism. If all VS pages currently fall back to `/og-default.png`, the fastest fix is to either:
- Generate a simple "VS" branded OG image template
- Or ensure each VS page frontmatter can set a custom `ogImage` prop

Since we can't run a full image generation pipeline easily, check if the current setup already supports per-page OG images via frontmatter or layout props. If yes, wire it up. If not, skip this step and note it.

### 3. Structured data on pricing/features
Check `landing/src/pages/pricing.astro` and `landing/src/pages/features.astro` for JSON-LD structured data (SoftwareApplication schema, Organization schema). 
- If missing, add appropriate JSON-LD `<script type="application/ld+json">` blocks.
- The Organization schema should include: name, url, sameAs (social links), logo.

### 4. GitHub org README
Check if crontinel has a GitHub organization (github.com/crontinel). If so, check if there's a `organization/README.md` or similar. Also check if any of the SDK repos have complete READMEs with the same quality/content.

## Branch naming
Use `feat/seo-quick-fixes` or `fix/seo-quick-fixes`

## PR
Create a PR against main. Title: `fix: quick SEO improvements (twitter card, structured data)`. No review needed — CCBot will merge.

## Working directory
`/Users/ray/Work/crontinel/landing/` for SEO fixes
`/Users/ray/Work/crontinel/` for GitHub org README
