<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Two-Factor Authentication — CompliFlow</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 480px;
            margin: 60px auto;
            padding: 0 24px;
            background: #f9fafb;
            color: #111827;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.06);
        }
        .brand { font-size: 0.8rem; font-weight: 600; color: #2563eb; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; }
        .subtitle { color: #6b7280; font-size: 0.95rem; margin: 0 0 28px; line-height: 1.5; }
        .step-label { font-size: 0.78rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 10px; }
        #qr-container { text-align: center; margin: 0 0 8px; min-height: 224px; }
        #qr-container img { border: 1px solid #e5e7eb; padding: 12px; border-radius: 12px; display: block; margin: 0 auto; }
        #manual-step { background: #f3f4f6; border-radius: 10px; padding: 12px 16px; margin: 16px 0 24px; display: none; }
        #manual-step p { font-size: 0.8rem; color: #6b7280; margin: 0 0 6px; }
        .secret-code {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 0.85rem;
            color: #111827;
            letter-spacing: 2px;
            white-space: nowrap;
            overflow-x: auto;
            display: block;
            padding-bottom: 2px;
        }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }

        /* ── 6-digit OTP input ── */
        .otp-label { font-size: 0.78rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px; }
        .otp-wrapper { display: flex; gap: 10px; justify-content: center; margin-bottom: 24px; }
        .otp-wrapper input {
            width: 52px;
            height: 64px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
            background: #fff;
            color: #111827;
            caret-color: transparent;
        }
        .otp-wrapper input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
            transform: scale(1.05);
        }
        .otp-wrapper input.filled {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .otp-wrapper input.error-state {
            border-color: #dc2626;
            background: #fef2f2;
            animation: shake 0.3s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        button {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            letter-spacing: 0.3px;
        }
        button:hover { background: #1d4ed8; }
        button:active { transform: scale(0.98); }
        button:disabled { background: #93c5fd; cursor: not-allowed; }
        .error {
            color: #dc2626;
            font-size: 0.875rem;
            text-align: center;
            margin-top: 12px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">CompliFlow</div>
        <h1>Two-Factor Setup</h1>
        <p class="subtitle">Scan the QR code with your authenticator app, then enter the 6-digit code to activate.</p>

        <div class="step-label">Step 1 — Scan QR Code</div>
        <div id="qr-container">
            <div style="color:#9ca3af;font-size:0.9rem;padding:40px 0;">Loading QR code…</div>
        </div>

        <div id="manual-step">
            <p>Can't scan? Enter this key manually:</p>
            <span id="secret-display" class="secret-code"></span>
        </div>

        <hr class="divider">

        <div class="otp-label">Step 2 — Enter 6-digit code</div>
        <div class="otp-wrapper" id="otp-wrapper">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
        </div>

        <button id="verify-btn" onclick="verifyCode()" disabled>Verify &amp; Enable MFA</button>
        <p class="error" id="error-msg"></p>

        <form method="POST" action="/mfa/logout" style="margin-top: 20px; text-align: center;">
            @csrf
            <button type="submit" style="background: none; box-shadow: none; color: #9ca3af; font-size: 0.82rem; font-weight: 500; padding: 0; width: auto; letter-spacing: 0; cursor: pointer; border: none;">
                Sign out
            </button>
        </form>
    </div>

    <script>
        // ── Load QR code ──────────────────────────────────────────────
        fetch('/mfa/setup', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('qr-container');
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=' + encodeURIComponent(data.qr_code_url);
            img.alt = 'MFA QR Code';
            img.width = 220;
            container.innerHTML = '';
            container.appendChild(img);
            document.getElementById('secret-display').textContent = data.secret;
            document.getElementById('manual-step').style.display = 'block';
        });

        // ── OTP input behaviour ───────────────────────────────────────
        const inputs = Array.from(document.querySelectorAll('.otp-wrapper input'));
        const btn    = document.getElementById('verify-btn');

        inputs.forEach((input, i) => {
            input.addEventListener('input', e => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val ? val[0] : '';

                if (val && i < 5) inputs[i + 1].focus();

                e.target.classList.toggle('filled', !!e.target.value);
                btn.disabled = getCode().length < 6;
            });

            input.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !input.value && i > 0) {
                    inputs[i - 1].value = '';
                    inputs[i - 1].classList.remove('filled');
                    inputs[i - 1].focus();
                }
                if (e.key === 'Enter' && getCode().length === 6) verifyCode();
            });

            // Handle paste on any digit
            input.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((char, j) => {
                    if (inputs[j]) {
                        inputs[j].value = char;
                        inputs[j].classList.add('filled');
                    }
                });
                if (pasted.length === 6) {
                    inputs[5].focus();
                    btn.disabled = false;
                }
            });
        });

        inputs[0].focus();

        function getCode() {
            return inputs.map(i => i.value).join('');
        }

        // ── Verify ───────────────────────────────────────────────────
        function verifyCode() {
            const code   = getCode();
            const errEl  = document.getElementById('error-msg');
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Verifying…';

            fetch('/mfa/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ code })
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                if (status === 200) {
                    btn.textContent = '✓ Verified!';
                    btn.style.background = '#16a34a';
                    setTimeout(() => window.location.href = '/admin', 600);
                } else {
                    inputs.forEach(i => i.classList.add('error-state'));
                    setTimeout(() => inputs.forEach(i => {
                        i.classList.remove('error-state');
                        i.value = '';
                        i.classList.remove('filled');
                    }), 600);
                    inputs[0].focus();
                    errEl.textContent = data.message || 'Invalid code. Please try again.';
                    errEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Verify & Enable MFA';
                }
            });
        }
    </script>
</body>
</html>
