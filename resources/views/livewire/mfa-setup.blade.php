<div>
<style>
    * { box-sizing: border-box; }
    .brand { font-size: 0.8rem; font-weight: 600; color: var(--primary-600, #2563eb); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; }
    .dark .brand { color: var(--primary-400, #60a5fa); }
    .mfa-h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; }
    .subtitle { color: #6b7280; font-size: 0.95rem; margin: 0 0 28px; line-height: 1.5; }
    .step-label { font-size: 0.78rem; font-weight: 600; color: var(--primary-600, #2563eb); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 10px; }
    .dark .step-label { color: var(--primary-400, #60a5fa); }
    #qr-container { text-align: center; margin: 0 0 8px; min-height: 224px; }
    #qr-container img { border: 1px solid #e5e7eb; padding: 12px; border-radius: 12px; display: block; margin: 0 auto; background: #fff; }
    #manual-step { background: #f3f4f6; border-radius: 10px; padding: 12px 16px; margin: 16px 0 24px; display: none; }
    #manual-step p { font-size: 0.8rem; color: #6b7280; margin: 0 0 6px; }
    .secret-code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem; color: #111827; letter-spacing: 2px; white-space: nowrap; overflow-x: auto; display: block; padding-bottom: 2px; }
    .dark #manual-step { background: rgb(17, 24, 39); }
    .dark #manual-step p { color: #9ca3af; }
    .dark .secret-code { color: #e5e7eb; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
    .otp-label { font-size: 0.78rem; font-weight: 600; color: var(--primary-600, #2563eb); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px; }
    .dark .otp-label { color: var(--primary-400, #60a5fa); }
    .otp-wrapper { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 24px; }
    .otp-wrapper input { width: 100%; height: 64px; text-align: center; font-size: 1.6rem; font-weight: 700; border: 2px solid #e5e7eb; border-radius: 12px; outline: none !important; transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s; background: #fff; color: #111827; caret-color: transparent; }
    .dark .otp-wrapper input { background: rgb(17, 24, 39); border-color: rgb(55, 65, 81); color: #f9fafb; }
    .dark .otp-wrapper input.error-state { background: #450a0a; }
    .otp-wrapper input:focus { border-color: var(--primary-600, #2563eb); box-shadow: 0 0 0 4px color-mix(in oklab, var(--primary-500, #3b82f6) 15%, transparent); transform: scale(1.05); }
    .otp-wrapper input.filled { border-color: var(--primary-500, #2563eb); }
    .otp-wrapper input.error-state { border-color: #dc2626; background: #fef2f2; animation: shake 0.3s ease; }
    .dark .otp-wrapper input.error-state { background: #450a0a; }
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    .verify-btn { width: 100%; padding: 14px; background: var(--primary-600, #2563eb); color: var(--primary-950, #fff) !important; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.15s, transform 0.1s; letter-spacing: 0.3px; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .verify-btn:hover:not(:disabled) { background: var(--primary-700, #1d4ed8); }
    .verify-btn:active:not(:disabled) { transform: scale(0.98); }
    .verify-btn:disabled { background: var(--primary-300, #93c5fd); cursor: not-allowed; }
    .verify-btn.success { background: #16a34a !important; }
    .verify-btn.loading { opacity: 0.8; cursor: not-allowed; }
    .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; display: none; }
    .verify-btn.loading .spinner { display: block; }
    .verify-btn.loading .btn-label { display: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .mfa-error { color: #dc2626; font-size: 0.875rem; text-align: center; margin-top: 12px; display: none; }
</style>
    <div class="brand">CompliFlow</div>
    <h1 class="mfa-h1">Two-Factor Setup</h1>
    <p class="subtitle">Scan the QR code with your authenticator app, then enter the 6-digit code to activate.</p>

    <div class="step-label">Step 1 — Scan QR Code</div>
    <div id="qr-container">
        @if($qrCodeUrl)
            <img src="{{ $qrCodeUrl }}" alt="MFA QR Code" width="220" style="border: 1px solid #e5e7eb; padding: 12px; border-radius: 12px; display: block; margin: 0 auto; background: #fff;">
        @else
            <div style="color:#9ca3af;font-size:0.9rem;padding:40px 0;">Loading QR code…</div>
        @endif
    </div>
    <div id="manual-step" style="display:block;">
        <p>Can't scan? Enter this key manually:</p>
        <span class="secret-code">{{ $secret }}</span>
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

    <button class="verify-btn" id="verify-btn" disabled>
        <span class="spinner"></span>
        <span class="btn-label">Verify &amp; Enable MFA</span>
    </button>
    <p class="mfa-error" id="error-msg"></p>
</div>

<script>
    document.getElementById('manual-step').style.display = 'block';

    const inputs   = Array.from(document.querySelectorAll('.otp-wrapper input'));
    const btn      = document.getElementById('verify-btn');
    const errEl    = document.getElementById('error-msg');
    let submitting = false;

    function getCode() { return inputs.map(i => i.value).join(''); }

    function setError(msg) {
        errEl.textContent = msg; errEl.style.display = 'block';
        inputs.forEach(i => i.classList.add('error-state'));
        setTimeout(() => {
            inputs.forEach(i => { i.classList.remove('error-state', 'filled'); i.value = ''; });
            btn.disabled = true; inputs[0].focus();
        }, 1000);
    }

    function submit() {
        if (submitting || getCode().length < 6) return;
        submitting = true; errEl.style.display = 'none';
        btn.classList.add('loading'); btn.disabled = true;
        @this.set('code', getCode());
        @this.call('verify').catch(() => { btn.classList.remove('loading'); btn.disabled = false; submitting = false; });
    }

    inputs.forEach((input, i) => {
        input.addEventListener('input', e => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val ? val[0] : '';
            if (val && i < 5) inputs[i + 1].focus();
            e.target.classList.toggle('filled', !!e.target.value);
            btn.disabled = getCode().length < 6;
            if (getCode().length === 6) submit();
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !input.value && i > 0) {
                inputs[i - 1].value = ''; inputs[i - 1].classList.remove('filled'); inputs[i - 1].focus(); btn.disabled = true;
            }
            if (e.key === 'Enter' && getCode().length === 6) submit();
        });
        input.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((char, j) => { if (inputs[j]) { inputs[j].value = char; inputs[j].classList.add('filled'); } });
            if (pasted.length === 6) { inputs[5].focus(); btn.disabled = false; submit(); }
        });
    });

    inputs[0].focus();

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('mfa-error', (params) => {
            const msg = Array.isArray(params) ? params[0] : (params.message || 'Invalid code. Please try again.');
            btn.classList.remove('loading'); btn.disabled = false; submitting = false; setError(msg);
        });
        Livewire.on('mfa-success', () => {
            btn.classList.remove('loading'); btn.classList.add('success');
            btn.innerHTML = '<span class="btn-label">✓ Verified!</span>';
        });
    });
</script>
