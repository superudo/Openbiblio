# mPDF Integration — Background & Decision Basis

This document summarises the investigation done on branch `enhancement/mpdf` to evaluate
replacing the embedded FPDF 1.53 (from 2004) with mPDF for PDF label generation.

## Problem

The existing label generation in `classes/PDF.php` and `classes/Lay.php` is built on a
stripped-down copy of FPDF 1.53, which has no UTF-8 / Unicode support. Book titles and
author names in Hebrew, Russian, Arabic, Chinese or any other non-Latin script cannot be
printed on labels. See discussion [#37](https://github.com/Larian77/Openbiblio/discussions/37)
for the original report and a proof-of-concept by dmitry-vs2019.

## Proposed Libraries

### 1. `mpdf/mpdf` ^8.3

- HTML-to-PDF rendering with full UTF-8 / Unicode support
- Right-to-left scripts (Hebrew, Arabic) handled automatically
- All required fonts bundled in the package (FreeSerif, Sun-ExtA, DejaVu, etc.) — no
  separate font installation
- Active maintenance, supports PHP 5.6 – 8.5
- Packagist: https://packagist.org/packages/mpdf/mpdf

### 2. `picqer/php-barcode-generator` ^2.0

mPDF's built-in barcode rendering requires the `ext-bcmath` PHP extension, which is
sometimes disabled on shared hosting. This library generates Code 128 barcodes as SVG
with no PHP extension dependencies at all. The SVG is embedded inline as a base64 data
URI, which mPDF renders natively.

- Pure PHP, zero extension requirements
- Packagist: https://packagist.org/packages/picqer/php-barcode-generator

## PHP Extension Requirements

| Extension  | Status   | Notes |
|------------|----------|-------|
| `mbstring` | Required | Enabled by default on virtually all PHP installations |
| `gd`       | Required | Bundled with PHP, standard on all shared hosts |
| `bcmath`   | Not needed | Bypassed by using picqer for barcode generation |

No OS-level packages, binaries (GhostScript, wkhtmltopdf etc.) or shell access required.

## Deployment on Shared Hosting

The project already bundles its dependencies in `vendor/` (committed to git), so no
Composer access is needed on the production server. The build workflow is:

1. On a local machine with PHP and Docker available, update dependencies:
   ```bash
   docker run --rm -v $(pwd):/app composer update --ignore-platform-reqs
   ```
   The `--ignore-platform-reqs` flag (or the `config.platform` override in `composer.json`)
   is needed when the build machine does not have `ext-gd` installed. The extension is
   only required at runtime on the actual server, not during the build step.

2. Commit the updated `vendor/`, `composer.json` and `composer.lock`.

3. Deploy as usual — copy files to the server (FTP, zip upload, git pull). No further
   steps needed.

## mPDF 8.x API — Key Differences from FPDF

The old embedded FPDF uses a low-level drawing API. mPDF 8.x takes HTML as input:

```php
require_once 'vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf([
    'mode'             => 'utf-8',
    'format'           => 'A4',
    'autoScriptToLang' => true,   // detect script, assign lang attribute
    'autoLangToFont'   => true,   // select appropriate font for each lang
]);

$mpdf->WriteHTML('<p lang="he" style="font-family: freeserif;">מלחמה ושלום</p>');
$mpdf->Output('label.pdf', 'I');
```

### Font selection

Font names correspond to the bundled TTF filenames in `vendor/mpdf/mpdf/ttfonts/`
(lowercase, without extension):

| Font name   | File              | Covers |
|-------------|-------------------|--------|
| `freeserif` | FreeSerif.ttf     | Latin, Cyrillic, Hebrew, Arabic, Greek |
| `sun-exta`  | Sun-ExtA.ttf      | CJK (Chinese, Japanese, Korean) |
| `dejavusans`| DejaVuSans.ttf    | Latin, Cyrillic, Greek |

RTL scripts require `direction: rtl` in CSS and `lang="he"` / `lang="ar"` on the element,
plus `autoScriptToLang` and `autoLangToFont` enabled.

### Barcode generation

```php
$generator = new Picqer\Barcode\BarcodeGeneratorSVG();
$svg       = $generator->getBarcode('AB000001', $generator::TYPE_CODE_128, 1, 40);
$html      = '<div style="text-align:center;">
    <img style="width:35mm; height:8mm;"
         src="data:image/svg+xml;base64,' . base64_encode($svg) . '" />
</div>';
```

Note: the `<img>` must be inside a block-level `<div>` — mPDF ignores `display:block`
on `<img>` elements.

## Test Page

`test_mpdf.php` (project root) generates a two-row label sheet covering Latin, Cyrillic,
Hebrew (RTL), Arabic (RTL), CJK, and Greek. Access it via the Docker web server to verify
rendering on any target environment before integrating into the label pipeline.

**Remove this file before a production release.**

## What Remains To Be Done

The libraries are in place and verified. To complete the integration:

1. Rewrite `classes/Lay.php` (or add a parallel implementation) using mPDF's HTML
   rendering instead of the FPDF drawing primitives.
2. Update the layout files in `layouts/default/` (`labels.php`, `barcode_33up.php`,
   `A4_barcode_1x16.php`, `mbr_labels.php`, `barcode_98up.php`) to emit HTML rather
   than calling the `Lay` drawing API.
3. Decide whether to keep FPDF as a fallback for existing installations or replace it
   outright. Given the security advisories on all mPDF 6.x releases and the incompatibility
   of the old API with PHP 8.x, a clean replacement is recommended.
