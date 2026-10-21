<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Achievement{{ $achievement->athlete ? ' - '.$achievement->athlete->name : '' }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #17182B; }

        /* --- screen only -------------------------------------------------- */
        .cert-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 24px; background: repeating-linear-gradient(45deg, #F1F3F8, #F1F3F8 14px, #E7EAF2 14px, #E7EAF2 28px); }
        .cert-shell.cert-shell-pdf { min-height: 0; display: block; padding: 0; background: #fff; }
        .cert-toolbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 50; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: center; padding: 14px; background: rgba(23, 24, 43, .88); }
        .cert-toolbar a { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 6px; font-size: 13px; font-weight: 700; text-decoration: none; }
        .cert-toolbar .cert-btn-primary { color: #fff; background: #10069F; }
        .cert-toolbar .cert-btn-primary:hover { background: #0C0480; }
        .cert-toolbar .cert-btn-ghost { color: #fff; background: rgba(255, 255, 255, .12); }
        .cert-toolbar .cert-btn-ghost:hover { background: rgba(255, 255, 255, .2); }

        /* --- certificate ---------------------------------------------------- */
        .cert { width: 277mm; margin: 0 auto; background: #fffdf8; box-shadow: 0 24px 60px rgba(23, 24, 43, .18); }
        .cert-shell-pdf .cert { width: auto; box-shadow: none; }
        .cert-frame { margin: 5mm; padding: 4mm; border: 1.6pt solid #b8922a; }
        .cert-frame-inner { padding: 4mm 5mm; border: 0.8pt solid #b8922a; }

        .cert-head { display: flex; align-items: center; justify-content: center; gap: 9mm; }
        .cert-logo { width: 22mm; height: 22mm; object-fit: contain; }
        .cert-seal { width: 22mm; height: 22mm; border-radius: 50%; border: 1.8pt solid #b8922a; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: .6mm; background: #fffdf8; color: #10069F; }
        .cert-seal strong { font-size: 9.5pt; letter-spacing: .5pt; }
        .cert-seal span { font-size: 3.4pt; letter-spacing: 1.1pt; color: #6B7280; }
        .cert-head h1 { margin: 0; color: #17182B; font-size: 14pt; font-weight: bold; letter-spacing: 2px; }
        .cert-head h2 { margin: 3pt 0 0; color: #10069F; font-size: 10.5pt; font-weight: bold; letter-spacing: 5px; }
        .cert-head p { margin: 1.5pt 0 0; color: #6B7280; font-size: 7pt; letter-spacing: 1.5px; }

        .cert-title { margin: 4mm 0 0; text-align: center; color: #10069F; font-size: 17pt; font-weight: bold; letter-spacing: 6pt; }
        .cert-rule { width: 40%; height: 1pt; margin: 2mm auto; background: #b8922a; }

        .cert-presented { margin: 3.5mm 0 0; text-align: center; color: #6B7280; font-size: 9.5pt; letter-spacing: 1.5px; }
        .cert-athlete { margin: 1.5mm 0 0; text-align: center; color: #17182B; font-size: 23pt; font-weight: bold; letter-spacing: 2pt; }
        .cert-athlete.is-long { font-size: 18pt; letter-spacing: 1pt; }
        .cert-athlete.is-very-long { font-size: 15pt; letter-spacing: .5pt; }
        .cert-recipient-label { margin: 1mm 0 0; text-align: center; color: #10069F; font-size: 7.5pt; font-weight: bold; letter-spacing: 2.5px; text-transform: uppercase; }
        .cert-student-id { margin: 1mm 0 0; text-align: center; color: #6B7280; font-size: 8.5pt; letter-spacing: 1px; }
        .cert-for { margin: 2.5mm 0 0; text-align: center; color: #17182B; font-size: 10pt; }
        .cert-for strong { color: #10069F; }
        .cert-line { margin: 2.5mm 0 0; text-align: center; color: #6B7280; font-size: 9pt; letter-spacing: 1px; }
        .cert-sport { margin: 1mm 0 0; text-align: center; color: #17182B; font-size: 15pt; font-weight: bold; letter-spacing: 1.5pt; }
        .cert-achievement { margin: 2.5mm 0 0; text-align: center; color: #17182B; font-size: 11pt; font-weight: bold; line-height: 1.3; }
        .cert-competition { margin: 1mm 0 0; text-align: center; color: #6B7280; font-size: 9.5pt; line-height: 1.3; }
        .cert-date { margin: 1.5mm 0 0; text-align: center; color: #17182B; font-size: 9.5pt; }

        .cert-signatures { display: flex; justify-content: space-between; gap: 20mm; margin: 7mm 14mm 0; }
        .cert-sign { width: 50mm; text-align: center; }
        .cert-sign p { margin: 0 0 2mm; font-size: 10pt; font-weight: bold; color: #17182B; min-height: 6mm; line-height: 1.25; }
        .cert-sign i { display: block; border-bottom: 1pt solid #17182B; }
        .cert-sign span { display: block; margin-top: 1.5mm; color: #6B7280; font-size: 8.5pt; letter-spacing: 1px; }

        .cert-foot { margin: 5mm 0 1mm; text-align: center; color: #10069F; font-size: 8pt; letter-spacing: 3px; }
        .cert-reference { margin: 1mm 0 0; text-align: center; color: #6B7280; font-size: 7.5pt; letter-spacing: 1.2px; }

        @media print {
            .cert-toolbar { display: none !important; }
            .cert-shell { padding: 0; background: #fff; }
            .cert, .cert-shell-pdf .cert { width: auto; box-shadow: none; }
        }
        @media screen and (max-width: 900px) {
            .cert-shell { align-items: flex-start; padding: 16px 0; }
            .cert { width: 100%; }
            .cert-head { gap: 4mm; }
        }
    </style>
</head>
<body>
    <div class="cert-shell{{ $pdf ? ' cert-shell-pdf' : '' }}">
        @unless ($pdf)
            <div class="cert-toolbar">
                <a class="cert-btn-primary" href="#" onclick="window.print(); return false;">Print</a>
                <a class="cert-btn-primary" href="{{ $pdfUrl }}">Download PDF</a>
                <a class="cert-btn-ghost" href="{{ $backUrl }}">Back to Achievement</a>
            </div>
        @endunless

        <div class="cert">
            <div class="cert-frame">
                <div class="cert-frame-inner">
                    <header class="cert-head">
                        @if (($use_vector_logo ?? false))
                            <span class="cert-seal"><strong>SNNHS</strong><span>EST. 1968</span></span>
                        @else
                            <img class="cert-logo" src="{{ $logo_src }}" alt="SNNHS logo" width="22mm" height="22mm">
                        @endif
                        <div>
                            <h1>{{ config('certificate.school') }}</h1>
                            <h2>{{ config('certificate.program') }}</h2>
                            <p>{{ config('certificate.committee') }}</p>
                        </div>
                    </header>

                    <p class="cert-title">{{ config('certificate.heading') }}</p>
                    <div class="cert-rule"></div>

                    <p class="cert-presented">{{ config('certificate.presented_to') }}</p>
                    <p class="cert-athlete @if (mb_strlen($recipientName) > 60) is-very-long @elseif ($longRecipient) is-long @endif">{{ $recipientName }}</p>
                    <p class="cert-recipient-label">{{ $recipientLabel }}</p>
                    @if ($recipientStudentId)
                        <p class="cert-student-id">Student ID: {{ $recipientStudentId }}</p>
                    @endif

                    <p class="cert-for">
                        for <strong>{{ $achievement->achievement_type }}</strong>
                        @if ($achievement->place)&nbsp;&middot;&nbsp;{{ $achievement->place }}@endif
                    </p>
                    <p class="cert-line">in the sport of</p>
                    <p class="cert-sport">{{ $achievement->sportLabel() }}</p>

                    <p class="cert-achievement">&ldquo;{{ $achievement->title }}&rdquo;</p>
                    @if ($achievement->competitionLabel())
                        <p class="cert-competition">{{ $achievement->competitionLabel() }}</p>
                    @endif
                    <p class="cert-date">Earned on {{ $achievement->dateAchievedLabel() }} &middot; Issued {{ $issuedAt->format('F j, Y') }}</p>

                    <div class="cert-signatures">
                        @foreach ($signatories as $signatory)
                            <div class="cert-sign">
                                <p>{{ $signatory['name'] ?: "\u{00A0}" }}</p>
                                <i></i>
                                <span>{{ $signatory['title'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="cert-foot">{{ config('certificate.footer') }}</p>
                    <p class="cert-reference">Certificate Reference No. {{ $reference }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>