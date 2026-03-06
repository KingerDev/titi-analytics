<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nová aktivita v e-shope</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111827; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .header { background: #4f46e5; padding: 28px 32px; }
        .header h1 { color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .header p { color: #c7d2fe; font-size: 13px; margin-top: 4px; }
        .body { padding: 28px 32px; }
        .section { margin-bottom: 28px; }
        .section:last-child { margin-bottom: 0; }
        .section-title { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 12px; }
        .section-title.orders { color: #1d4ed8; }
        .section-title.registrations { color: #15803d; }
        .icon { width: 18px; height: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; padding: 0 10px 8px; border-bottom: 1px solid #e5e7eb; }
        th:last-child { text-align: right; }
        td { padding: 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        td:last-child { text-align: right; }
        tr:last-child td { border-bottom: none; }
        .order-id { font-weight: 600; color: #1d4ed8; }
        .meno { color: #374151; }
        .email-text { color: #6b7280; font-size: 12px; }
        .suma { font-weight: 600; color: #111827; white-space: nowrap; }
        .date { color: #9ca3af; font-size: 12px; white-space: nowrap; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
        .footer a { color: #6366f1; text-decoration: none; }
        .divider { height: 1px; background: #e5e7eb; margin: 0 -32px 28px; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>Nová aktivita v e-shope</h1>
        <p>{{ now()->format('d.m.Y H:i') }} &nbsp;·&nbsp;
            @if($orders->isNotEmpty()) {{ $orders->count() }} {{ $orders->count() === 1 ? 'objednávka' : (in_array($orders->count(), [2,3,4]) ? 'objednávky' : 'objednávok') }}@endif
            @if($orders->isNotEmpty() && $registrations->isNotEmpty()) &nbsp;·&nbsp; @endif
            @if($registrations->isNotEmpty()) {{ $registrations->count() }} {{ $registrations->count() === 1 ? 'registrácia' : (in_array($registrations->count(), [2,3,4]) ? 'registrácie' : 'registrácií') }}@endif
        </p>
    </div>

    <div class="body">

        {{-- ── Objednávky ─────────────────────────────────── --}}
        @if($orders->isNotEmpty())
        <div class="section">
            <div class="section-title orders">
                <svg class="icon" fill="none" stroke="#1d4ed8" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3A1 1 0 006 17h12m-5 3a1 1 0 11-2 0 1 1 0 012 0zm6 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
                Nové objednávky
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Zákazník</th>
                        <th>Dátum</th>
                        <th>Suma</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $o)
                    <tr>
                        <td><span class="order-id">#{{ $o->order_id }}</span></td>
                        <td>
                            <div class="meno">{{ trim($o->meno) ?: 'Neznámy zákazník' }}</div>
                            @if($o->zakaznik_email)
                            <div class="email-text">{{ $o->zakaznik_email }}</div>
                            @endif
                        </td>
                        <td class="date">{{ \Carbon\Carbon::parse($o->date_added)->format('d.m.Y H:i') }}</td>
                        <td class="suma">{{ number_format($o->total_sdph ?? 0, 2, ',', ' ') }} €</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @php
                $totalSuma = $orders->sum('total_sdph');
            @endphp
            @if($orders->count() > 1)
            <p style="text-align:right; font-size:13px; font-weight:600; color:#111827; padding-top:10px; border-top:1px solid #e5e7eb; margin-top:4px;">
                Spolu: {{ number_format($totalSuma, 2, ',', ' ') }} €
            </p>
            @endif
        </div>
        @endif

        {{-- Divider medzi sekciami --}}
        @if($orders->isNotEmpty() && $registrations->isNotEmpty())
        <div class="divider"></div>
        @endif

        {{-- ── Registrácie ─────────────────────────────────── --}}
        @if($registrations->isNotEmpty())
        <div class="section">
            <div class="section-title registrations">
                <svg class="icon" fill="none" stroke="#15803d" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Nové registrácie
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Meno</th>
                        <th>E-mail</th>
                        <th>Registrovaný</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($registrations as $r)
                    <tr>
                        <td class="meno">
                            {{ trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) ?: '—' }}
                        </td>
                        <td class="email-text" style="color:#374151">{{ $r->email }}</td>
                        <td class="date">{{ \Carbon\Carbon::parse($r->date_added)->format('d.m.Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>

    <div class="footer">
        Táto správa bola odoslaná automaticky &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}">Otvoriť analytiku</a>
    </div>

</div>
</body>
</html>
