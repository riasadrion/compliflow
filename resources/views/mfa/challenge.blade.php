<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification — CompliFlow</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0 24px;
            background: linear-gradient(135deg, #eff6ff 0%, #f9fafb 50%, #f0fdf4 100%);
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 44px 40px 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 12px 40px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .shield {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.6rem;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .brand {
            font-size: 0.72rem;
            font-weight: 700;
            color: #2563eb;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 1.45rem;
            font-weight: 700;
            margin: 0 0 10px;
            color: #111827;
        }
        .subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0 0 32px;
            line-height: 1.6;
        }
        .otp-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .otp-wrapper {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }
        .otp-wrapper input {
            width: 100%;
            height: 64px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s, background 0.15s;
            background: #f9fafb;
            color: #111827;
            caret-color: transparent;
        }
        .otp-wrapper input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
            transform: scale(1.05);
            background: #fff;
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
            25%  { transform: translateX(-4px); }
            75%  { transform: translateX(4px); }
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
        }
        button:hover:not(:disabled) { opacity: 0.92; box-shadow: 0 6px 16px rgba(37,99,235,0.3); }
        button:active:not(:disabled) { transform: scale(0.98); }
        button:disabled { background: #bfdbfe; box-shadow: none; cursor: not-allowed; }
        .error {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 14px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="shield">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L4 6V12C4 16.418 7.582 20.418 12 22C16.418 20.418 20 16.418 20 12V6L12 2Z" fill="white" fill-opacity="0.15" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                <circle cx="12" cy="11" r="2.5" stroke="white" stroke-width="1.5"/>
                <path d="M12 13.5V16.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="brand">CompliFlow</div>
        <h1>Verification Required</h1>
        <p class="subtitle">Enter the 6-digit code from your authenticator app to continue.</p>

        <div class="otp-label">Enter 6-digit code</div>
        <div class="otp-wrapper" id="otp-wrapper">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d">
        </div>

        <button id="verify-btn" onclick="submitChallenge()" disabled>Verify</button>
        <p class="error" id="error-msg"></p>

        <form method="POST" action="/mfa/logout" style="margin-top: 20px;">
            @csrf
            <button type="submit" style="background: none; box-shadow: none; color: #9ca3af; font-size: 0.82rem; font-weight: 500; padding: 0; width: auto; letter-spacing: 0;">
                Sign out
            </button>
        </form>
    </div>

    <script>
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
                if (e.key === 'Enter' && getCode().length === 6) submitChallenge();
            });

            input.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((char, j) => {
                    if (inputs[j]) {
                        inputs[j].value = char;
                        inputs[j].classList.add('filled');
                    }
                });
                if (pasted.length === 6) { inputs[5].focus(); btn.disabled = false; }
            });
        });

        inputs[0].focus();

        function getCode() {
            return inputs.map(i => i.value).join('');
        }

        function submitChallenge() {
            const code  = getCode();
            const errEl = document.getElementById('error-msg');
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Verifying…';

            fetch('/mfa/challenge', {
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
                    btn.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
                    btn.style.boxShadow = '0 4px 12px rgba(22,163,74,0.3)';
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
                    btn.textContent = 'Verify';
                }
            });
        }
    </script>
</body>
</html>
