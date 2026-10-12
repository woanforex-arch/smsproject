<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets — {{ $event->title }}</title>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f9fafb;
            color: #111827;
        }
        .toolbar {
            max-width: 800px;
            margin: 0 auto 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .toolbar button {
            padding: 10px 18px;
            border-radius: 8px;
            border: 0;
            background: #111827;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .hint { font-size: 13px; color: #6b7280; }

        .ticket {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .left {
            flex: 1;
            padding: 24px;
        }
        .right {
            width: 220px;
            padding: 24px;
            border-left: 2px dashed #d1d5db;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            background: #f59e0b;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
        }
        h1 { font-size: 20px; margin: 10px 0 16px; }
        dl { margin: 0; font-size: 13px; }
        dt { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; margin-top: 12px; }
        dd { margin: 2px 0 0; font-weight: 600; }
        .ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 18px; letter-spacing: .08em; }
        .footer { max-width: 800px; margin: 0 auto; font-size: 12px; color: #6b7280; text-align: center; padding: 12px 0 32px; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <span class="hint">{{ $tickets->count() }} ticket(s) — booking ref <strong>{{ $registration?->reference }}</strong></span>
    </div>

    @foreach ($tickets as $ticket)
        <div class="ticket">
            <div class="left">
                <span class="badge">E-Ticket</span>
                <h1>{{ $event->title }}</h1>

                <dl>
                    <dt>Attendee</dt>
                    <dd>{{ $ticket->attendee_name }}</dd>

                    <dt>Date &amp; time</dt>
                    <dd>{{ $event->starts_at->format('l, j F Y') }} &middot; {{ $event->starts_at->format('g:i a') }}</dd>

                    @if ($event->venue)
                        <dt>Venue</dt>
                        <dd>
                            {{ $event->venue }}
                            @if ($event->city), {{ $event->city }}@endif
                        </dd>
                    @endif

                    <dt>Ticket type</dt>
                    <dd>{{ $ticket->ticketType->name }}</dd>

                    <dt>Ticket reference</dt>
                    <dd class="ref">{{ $ticket->reference }}</dd>

                    <dt>Booking reference</dt>
                    <dd style="font-family: ui-monospace, monospace;">{{ $registration?->reference }}</dd>
                </dl>
            </div>

            <div class="right">
                {!! QrCode::format('svg')->size(170)->margin(0)->generate($ticket->qrPayload()) !!}
                <span style="font-size:11px; color:#6b7280; text-align:center;">
                    Scan at the door<br>
                    or quote the reference
                </span>
            </div>
        </div>
    @endforeach

    <div class="footer">
        Keep this page safe. A duplicate copy has also been emailed to {{ $registration?->email }}.
    </div>
</body>
</html>