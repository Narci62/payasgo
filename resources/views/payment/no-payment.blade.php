<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Trueline MDM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #F4F6FA;
            color: #16213E;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
        }

        /* ---------- Header ---------- */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 4px 24px;
        }

        .topbar h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .avatar-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00D4AA 0%, #0099FF 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-icon svg {
            width: 20px;
            height: 20px;
            fill: #fff;
        }

        /* ---------- Bannière statut ---------- */
        .status-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .status-banner.compliant {
            background: #E8FBF5;
            border-color: #B7F0DE;
        }

        .status-banner.restricted {
            background: #FFF7E6;
            border-color: #FCE3AD;
        }

        .status-banner.non_compliant {
            background: #FFEDED;
            border-color: #FDC6C6;
        }

        .status-banner .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .status-banner.compliant .icon-circle { background: #00D4AA; }
        .status-banner.restricted .icon-circle { background: #FFB800; }
        .status-banner.non_compliant .icon-circle { background: #FF6B6B; }

        .status-banner .icon-circle svg {
            width: 16px;
            height: 16px;
            fill: #fff;
        }

        .status-banner .texts strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
        }

        .status-banner.compliant .texts strong { color: #00A884; }
        .status-banner.restricted .texts strong { color: #C98A00; }
        .status-banner.non_compliant .texts strong { color: #E14E4E; }

        .status-banner .texts span {
            font-size: 12.5px;
            color: #6B7686;
        }

        /* ---------- Carte principale ---------- */
        .card {
            background: #fff;
            border: 1px solid #ECEFF4;
            border-radius: 24px;
            padding: 28px 24px 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(16, 24, 64, 0.05);
        }

        .progress-circle {
            width: 200px;
            height: 200px;
            margin: 0 auto 24px;
            position: relative;
        }

        .progress-circle svg {
            transform: rotate(-90deg);
        }

        .progress-circle-bg {
            fill: none;
            stroke: #EEF1F7;
            stroke-width: 16;
        }

        .progress-circle-fill {
            fill: none;
            stroke: url(#gradient);
            stroke-width: 16;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .progress-text h2 {
            font-size: 46px;
            font-weight: 800;
            color: #16213E;
            line-height: 1;
        }

        .progress-text p {
            color: #8B98B0;
            font-size: 13.5px;
            margin-top: 6px;
        }

        /* ---------- Bloc identité (avatar + nom + device) ---------- */
        .identity-row {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #F7F8FC;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .identity-row .avatar-square {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00D4AA 0%, #0099FF 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .identity-row .avatar-square svg {
            width: 22px;
            height: 22px;
            fill: #fff;
        }

        .identity-row .name {
            font-size: 16px;
            font-weight: 700;
            color: #16213E;
        }

        .identity-row .device {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #8B98B0;
            margin-top: 2px;
        }

        .identity-row .device svg {
            width: 13px;
            height: 13px;
            fill: #8B98B0;
        }

        /* ---------- Section Informations ---------- */
        .section-title {
            font-size: 20px;
            font-weight: 800;
            margin: 4px 4px 14px;
            color: #16213E;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .info-item {
            background: #fff;
            border: 1px solid #ECEFF4;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 6px 16px rgba(16, 24, 64, 0.04);
        }

        .info-item .icon-box {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .info-item .icon-box svg {
            width: 17px;
            height: 17px;
        }

        .icon-box.blue { background: #E7F0FF; }
        .icon-box.blue svg { fill: #0099FF; }

        .icon-box.teal { background: #E5FBF5; }
        .icon-box.teal svg { fill: #00D4AA; }

        .icon-box.green { background: #E7FBEA; }
        .icon-box.green svg { fill: #34C759; }

        .icon-box.amber { background: #FFF3DE; }
        .icon-box.amber svg { fill: #FFB800; }

        .info-item label {
            display: block;
            color: #8B98B0;
            font-size: 12.5px;
            margin-bottom: 6px;
        }

        .info-item .value {
            font-size: 15px;
            font-weight: 700;
            color: #16213E;
            word-break: break-word;
        }

        .info-item .value.mono {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 14px;
        }

        .info-item .value.c-green { color: #00A884; }
        .info-item .value.c-amber { color: #C98A00; }

        footer {
            text-align: center;
            color: #B3BACB;
            font-size: 12px;
            margin-top: 32px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- Header -->
        <div class="topbar">
            <h1>Trueline MDM</h1>
            <div class="avatar-icon">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
            </div>
        </div>

        <!-- Bannière de conformité -->
        <div class="status-banner {{ $compliance_status }}">
            <div class="icon-circle">
                @if($compliance_status == 'compliant')
                    <svg viewBox="0 0 24 24"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
                @elseif($compliance_status == 'restricted')
                    <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm5 13.6L15.6 17 12 13.4 8.4 17 7 15.6l3.6-3.6L7 8.4 8.4 7l3.6 3.6L15.6 7 17 8.4 13.4 12z"/></svg>
                @endif
            </div>
            <div class="texts">
                @if($compliance_status == 'compliant')
                    <strong>CONFORME</strong>
                    <span>Appareil conforme aux politiques</span>
                @elseif($compliance_status == 'restricted')
                    <strong>ACCÈS RESTREINT</strong>
                    <span>Vérification requise sous peu</span>
                @else
                    <strong>NON CONFORME</strong>
                    <span>Action requise sur l'appareil</span>
                @endif
            </div>
        </div>

        <!-- Carte principale -->
        <div class="card">
            <div class="progress-circle">
                <svg width="200" height="200">
                    <defs>
                        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#00D4AA"/>
                            <stop offset="100%" style="stop-color:#0099FF"/>
                        </linearGradient>
                    </defs>
                    <circle class="progress-circle-bg" cx="100" cy="100" r="84"/>
                    <circle class="progress-circle-fill" cx="100" cy="100" r="84"
                            stroke-dasharray="527.7875658030853"
                            stroke-dashoffset="{{ 527.7875658030853 * (1 - $compliance_percentage / 100) }}"/>
                </svg>
                <div class="progress-text">
                    <h2>{{ $compliance_percentage }}%</h2>
                    <p>Conformité</p>
                </div>
            </div>

            <div class="identity-row">
                <div class="avatar-square">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
                </div>
                <div>
                    <div class="name">{{ $employee_name }}</div>
                    <div class="device">
                        <svg viewBox="0 0 24 24"><path d="M17 1H7c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V4h10v15z"/></svg>
                        {{ $device_model }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Informations -->
        <div class="section-title">Informations</div>

        <div class="info-grid">
            <div class="info-item">
                <div class="icon-box blue">
                    <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                </div>
                <label>Département</label>
                <div class="value">{{ $department }}</div>
            </div>

            <div class="info-item">
                <div class="icon-box teal">
                    <svg viewBox="0 0 24 24"><path d="M17.81 4.47c-.08 0-.16-.02-.23-.06C15.66 3.42 14 3 12.01 3c-1.98 0-3.86.47-5.57 1.41-.24.13-.54.04-.68-.2-.13-.24-.04-.55.2-.68C7.82 2.52 9.86 2 12.01 2c2.13 0 3.99.47 6.03 1.52.25.13.34.43.21.68-.09.18-.26.27-.44.27M3.5 9.72c-.1 0-.2-.03-.29-.09-.23-.16-.28-.47-.12-.7.99-1.4 2.25-2.5 3.75-3.27C9.98 4.24 14 4.24 17.15 5.65c1.5.78 2.76 1.87 3.75 3.27.16.22.11.53-.12.7-.23.16-.53.11-.7-.12-.9-1.26-2.04-2.24-3.39-2.93-2.87-1.31-6.35-1.31-9.24-.01-1.36.7-2.5 1.68-3.4 2.94-.09.15-.24.22-.4.22m6.02 12.63c-.13 0-.26-.05-.35-.15-.85-.85-1.31-1.4-1.98-2.62-.68-1.25-1.04-2.79-1.04-4.44 0-3 2.51-5.44 5.6-5.44s5.6 2.44 5.6 5.44c0 .28-.23.5-.5.5s-.5-.22-.5-.5c0-2.44-2.06-4.44-4.6-4.44s-4.6 2-4.6 4.44c0 1.5.32 2.85.94 3.99.64 1.15 1.06 1.64 1.85 2.42.2.2.2.51 0 .71-.1.1-.23.15-.35.15z"/></svg>
                </div>
                <label>Device ID</label>
                <div class="value mono">{{ $device_id }}</div>
            </div>

            <div class="info-item">
                <div class="icon-box green">
                    <svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                </div>
                <label>Dernière sync</label>
                <div class="value c-green mono">{{ $last_sync }}</div>
            </div>

            <div class="info-item">
                <div class="icon-box amber">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                </div>
                <label>Prochaine vérif.</label>
                <div class="value c-amber">{{ $next_check }}</div>
            </div>
        </div>

        <footer>
            © 2026 Trueline<br>
            Gestion de flotte mobile
        </footer>
    </div>
</body>
</html>
