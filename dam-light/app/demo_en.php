<?php
declare(strict_types=1);

/** Engelse teksten voor de demo-inhoud. */
if (!function_exists('demo_label_en')) {
    function demo_label_en(string $nl, array $labels): string
    {
        if (isset($labels[$nl])) return $labels[$nl];
        if (str_starts_with($nl, 'Steunkleur – ')) return 'Spot colour – ' . substr($nl, strlen('Steunkleur – '));
        return '';
    }
}
return ['assets' => [
    'Logo – primair (full colour)' => ['Logo – primary (full colour)', "The standard logo. Use it on white or light backgrounds.\nKeep a clear space around the logo of at least the height of the 'N'."],
    'Logo – wit (diapositief)' => ['Logo – white (reversed)', 'For use on dark backgrounds and photos. Note: the brand mark is white with a navy N.'],
    'Logo – zwart (monochroom)' => ['Logo – black (monochrome)', 'For single-colour print, forms, engravings and stamps.'],
    'Beeldmerk (icoon)' => ['Brand mark (icon)', 'The brand mark only, without the wordmark. For app icons, social profile pictures and small applications.'],
    'Roll-up – Product range 2026' => ['Roll-up – Product range 2026', 'General product roll-up for trade shows and showrooms. English, usable worldwide.'],
    'Roll-up – Service & Support' => ['Roll-up – Service & Support', 'German-language roll-up focusing on service, maintenance and training.'],
    'Roll-up – Beurs Benelux' => ['Roll-up – Trade show Benelux', 'Dutch-language trade show roll-up with space for a booth number. Have HQ update the booth number before printing.'],
    'Roll-up – Product range 2026 (FR)' => ['Roll-up – Product range 2026 (FR)', 'French translation – still being reviewed by NORVIQ France.'],
    'Beurswand 3×3 – Precision' => ['Backwall 3×3 – Precision', 'Backwall for a 3×3 pop-up booth. Subsidiaries only: trade show participation by partners is arranged through HQ.'],
    'Flyer – Beursactie 2026' => ['Flyer – Trade show offer 2026', 'Double-sided A5 flyer for trade show visitors.'],
    'Productbrochure 2026' => ['Product brochure 2026', 'Four-page brochure covering the full range. Print-ready; HQ can add your own contact details on the back.'],
    'LinkedIn-banner – Product range' => ['LinkedIn banner – Product range', 'Cover image for LinkedIn company pages and personal profiles.'],
    'Social post – Meet us at the fair' => ['Social post – Meet us at the fair', 'Square post for LinkedIn, Instagram and Facebook in the run-up to a trade show.'],
    'E-mailhandtekening banner' => ['Email signature banner', 'Banner below your email signature in Outlook.'],
    'Productfoto – X200' => ['Product photo – X200', 'Cut-out product photo. Do not credit a photographer; rights have been bought out worldwide.'],
    'Huisstijlgids 2026' => ['Brand guidelines 2026', 'Colours (CMYK, PMS, RGB, HEX), logo usage and typography in one place.'],
],
'labels' => [
    'Drukwerk – CMYK (PDF, vector)' => 'Print – CMYK (PDF, vector)',
    'Web & Office – PNG transparant (2400 px)' => 'Web & Office – transparent PNG (2400 px)',
    'Web – SVG (vector, schaalbaar)' => 'Web – SVG (vector, scalable)',
    'Drukklare PDF – CMYK, 5 mm afloop + snijtekens' => 'Print-ready PDF – CMYK, 5 mm bleed + crop marks',
    'Digitale proef – JPG (ter goedkeuring / social)' => 'Digital proof – JPG (for approval / social)',
    'Drukklare PDF – CMYK (concept)' => 'Print-ready PDF – CMYK (draft)',
    'Drukklare PDF – CMYK, 5 mm afloop' => 'Print-ready PDF – CMYK, 5 mm bleed',
    'Digitale proef – JPG' => 'Digital proof – JPG',
    'Drukklare PDF – CMYK, 3 mm afloop' => 'Print-ready PDF – CMYK, 3 mm bleed',
    'LinkedIn-omslag – PNG' => 'LinkedIn cover – PNG',
    'Hoge resolutie – JPG 3000 px (drukwerk)' => 'High resolution – JPG 3000 px (print)',
    'Huisstijlgids – PDF' => 'Brand guidelines – PDF',
],
];
