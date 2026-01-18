<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise AMAPI Créée</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-icon svg {
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

        .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 32px;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .info-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            color: #1f2937;
            font-size: 16px;
            font-family: 'Monaco', 'Courier New', monospace;
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            word-break: break-all;
        }

        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 8px;
            transition: background 0.3s;
        }

        .copy-btn:hover {
            background: #5568d3;
        }

        .copy-btn:active {
            background: #4c5fd5;
        }

        .next-steps {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
            text-align: left;
        }

        .next-steps h3 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .next-steps ol {
            color: #1e3a8a;
            padding-left: 20px;
        }

        .next-steps li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .next-steps code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            color: #be123c;
        }

        .admin-info {
            color: #6b7280;
            font-size: 14px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <h1>✅ Enterprise AMAPI Créée !</h1>
        <p class="subtitle">Votre entreprise Android Management a été configurée avec succès</p>

        <div class="info-box">
            <div class="info-label">Enterprise ID</div>
            <div class="info-value" id="enterpriseId">{{ $enterprise_id }}</div>
            <button class="copy-btn" onclick="copyToClipboard()">📋 Copier</button>
        </div>

        @if(isset($admin_email))
        <div class="admin-info">
            👤 Administrateur : <strong>{{ $admin_email }}</strong>
        </div>
        @endif

        <div class="next-steps">
            <h3>🎯 Prochaines étapes</h3>
            <ol>
                <li>L'ENTERPRISE_ID a été automatiquement ajouté à votre fichier .env</li>
                <li>Créez les politiques :
                    <br><code>php artisan amapi:create-policies</code>
                </li>
                <li>Testez la configuration :
                    <br><code>php artisan amapi:test</code>
                </li>
                <li>Vous pouvez maintenant fermer cette fenêtre</li>
            </ol>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const text = document.getElementById('enterpriseId').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✅ Copié !';
                btn.style.background = '#10b981';

                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#667eea';
                }, 2000);
            });
        }
    </script>
</body>
</html>
