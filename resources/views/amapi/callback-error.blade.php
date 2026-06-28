<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur AMAPI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: #ef4444;
            border-radius: 50%;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-icon svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 3;
            fill: none;
        }

        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .error-message {
            color: #6b7280;
            font-size: 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }

        .help-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
        }

        .help-box h3 {
            color: #1f2937;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .help-box ul {
            color: #4b5563;
            padding-left: 20px;
            line-height: 1.8;
        }

        .retry-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 24px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .retry-btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <svg viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>

        <h1>❌ Erreur d'enrollment</h1>

        <div class="error-message">
            {{ $error }}
        </div>

        <div class="help-box">
            <h3>💡 Solutions possibles</h3>
            <ul>
                <li>Vérifiez que l'API Android Management est activée</li>
                <li>Assurez-vous que le service account a les bonnes permissions</li>
                <li>Attendez quelques minutes et réessayez</li>
                <li>Contactez le support si le problème persiste</li>
            </ul>
        </div>

        <a href="{{ config('app.url') }}/admin" class="retry-btn">
            🔄 Retour au dashboard
        </a>
    </div>
</body>
</html>
