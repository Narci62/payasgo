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
            background: linear-gradient(135deg, #0A1128 0%, #1B2D5A 100%);
            color: #fff;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            padding: 40px 0;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            color: #8B98B0;
            font-size: 14px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .progress-circle {
            width: 200px;
            height: 200px;
            margin: 20px auto;
            position: relative;
        }

        .progress-circle svg {
            transform: rotate(-90deg);
        }

        .progress-circle-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
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
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .progress-text p {
            color: #8B98B0;
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
        }

        .info-item label {
            color: #8B98B0;
            font-size: 12px;
            display: block;
            margin-bottom: 8px;
        }

        .info-item .value {
            font-size: 18px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .status-compliant {
            background: rgba(0, 212, 170, 0.2);
            color: #00D4AA;
            border: 1px solid rgba(0, 212, 170, 0.3);
        }

        .status-restricted {
            background: rgba(255, 184, 0, 0.2);
            color: #FFB800;
            border: 1px solid rgba(255, 184, 0, 0.3);
        }

        .status-non-compliant {
            background: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $employee_name }}</h1>
            <p>{{ $device_model }}</p>
        </div>

        <div class="card">
            <center>
                <span class="status-badge status-{{ $compliance_status }}">
                    @if($compliance_status == 'compliant')
                        ✓ CONFORME
                    @elseif($compliance_status == 'restricted')
                        ⚠ ACCÈS RESTREINT
                    @else
                        ✕ NON CONFORME
                    @endif
                </span>
            </center>

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

            <div class="info-grid">
                <div class="info-item">
                    <label>Total</label>
                    <div class="value">{{ $total_price }} F</div>
                </div>
                <div class="info-item">
                    <label>Payé</label>
                    <div class="value" style="color: #00D4AA;">{{ $paid_amount }} F</div>
                </div>
                <div class="info-item">
                    <label>Reste</label>
                    <div class="value" style="color: #FF6B6B;">{{ $remaining_amount }} F</div>
                </div>
                <div class="info-item">
                    <label>Échéance</label>
                    <div class="value" style="color: #FFB800;">{{ $due_date }}</div>
                </div>
            </div>
            <!-- lien pour effectuer un paiement -->
            <div style="text-align: center; margin-top: 35px;">
                <a href="{{ route('payment.form', ['imat' => $reference]) }}" class="info-item" style="text-decoration: none;color: #ffffff;">Effectuer un paiement</a>
            </div>
        </div>

        <p style="text-align: center; color: #8B98B0; font-size: 12px; margin-top: 40px;">
            © 2026 Trueline<br>
            Gestion de flotte mobile
        </p>
    </div>
</body>
</html>
