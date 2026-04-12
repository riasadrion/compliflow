<x-filament-panels::page.simple>
    <x-filament::section>
        <style>
            .otp-grid {
                display: grid;
                grid-template-columns: repeat(6, 1fr);
                gap: 10px;
                margin-bottom: 20px;
            }
            .otp-input {
                width: 100%;
                height: 60px;
                text-align: center;
                font-size: 1.5rem;
                font-weight: 700;
                border: 2px solid var(--gray-300, #d1d5db);
                border-radius: 12px;
                outline: none;
                background: transparent;
                color: inherit;
                caret-color: transparent;
                transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
            }
            .otp-input:focus {
                border-color: var(--primary-500, #f97316);
                box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
                transform: scale(1.05);
            }
            .otp-input.filled { border-color: var(--primary-500, #f97316); }
            .otp-input.error-state {
                border-color: #dc2626;
                animation: otp-shake 0.3s ease;
            }
            {{ '@' }}keyframes otp-shake {
                0%, 100% { transform: translateX(0); }
                25%  { transform: translateX(-4px); }
                75%  { transform: translateX(4px); }
            }
            .qr-wrap {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                margin-bottom: 20px;
            }
            .qr-wrap img {
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,0.1);
                padding: 8px;
                background: #fff;
            }
            .secret-box {
                width: 100%;
                border-radius: 10px;
                background: rgba(0,0,0,0.15);
                padding: 10px 14px;
                font-size: 0.75rem;
                color: #9ca3af;
            }
            .secret-box code {
                display: block;
                font-family: 'SF Mono', 'Fira Code', monospace;
                font-size: 0.82rem;
                letter-spacing: 2px;
                white-space: nowrap;
                overflow-x: auto;
                color: inherit;
                margin-top: 4px;
            }
            .step-label {
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: var(--primary-500, #f97316);
                margin-bottom: 10px;
            }
            .otp-divider {
                border: none;
                border-top: 1px solid rgba(255,255,255,0.08);
                margin: 16px 0 20px;
            }
            .otp-verify-btn {
                width: 100%;
                padding: 12px;
                border-radius: 10px;
                font-size: 0.95rem;
                font-weight: 600;
                border: none;
                cursor: pointer;
                background: var(--primary-500, #f97316);
                color: #fff;
                transition: opacity 0.15s;
                margin-bottom: 4px;
            }
            .otp-verify-btn:disabled { opacity: 0.45; cursor: not-allowed; }
            .otp-verify-btn:hover:not(:disabled) { opacity: 0.88; }
            .otp-verify-btn.success { background: #16a34a; }
            .otp-verify-btn.loading { opacity: 0.75; cursor: not-allowed; }
            .otp-spinner {
                width: 16px; height: 16px;
                border: 2px solid rgba(255,255,255,0.4);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin 0.6s linear infinite;
                display: none;
            }
            .otp-verify-btn.loading .otp-spinner { display: block; }
            .otp-verify-btn.loading .btn-label { display: none; }
            {{ '@' }}keyframes spin { to { transform: rotate(360deg); } }
            .otp-error {
                font-size: 0.85rem; color: #dc2626;
                text-align: center; margin-bottom: 12px; display: none;
            }
            .otp-signout {
                display: block;
                width: 100%;
                text-align: center;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 0.8rem;
                color: #9ca3af;
                margin-top: 12px;
                padding: 0;
            }
            .otp-signout:hover { color: #6b7280; }
            .otp-error {
                font-size: 0.85rem;
                color: #dc2626;
                text-align: center;
                margin-bottom: 12px;
            }
        </style>

        {{-- QR Code --}}
        <div class="step-label">Step 1 — Scan QR Code</div>
        <div class="qr-wrap">
            @if ($qrCodeUrl)
                <img src="{{ $qrCodeUrl }}" alt="MFA QR Code" width="200" height="200" />
            @endif
            <div class="secret-box">
                <span>Can't scan? Enter this key manually:</span>
                <code>{{ $secret }}</code>
            </div>
        </div>

        <hr class="otp-divider" />

        {{-- OTP --}}
        <div class="step-label">Step 2 — Enter 6-digit code</div>
        <div class="otp-grid" id="otp-wrapper">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code" autofocus class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
        </div>

        @if ($errorMessage)
            <p class="otp-error">{{ $errorMessage }}</p>
        @endif

        <p class="otp-error" id="otp-error"></p>

        <button class="otp-verify-btn" id="verify-btn" disabled>
            <span class="otp-spinner"></span>
            <span class="btn-label">Verify &amp; Enable MFA</span>
        </button>


        <script>
            const inputs   = Array.from(document.querySelectorAll('.otp-input'));
            const btn      = document.getElementById('verify-btn');
            const errEl    = document.getElementById('otp-error');
            let otpValue   = '';
            let submitting = false;

            function getCode() { return inputs.map(i => i.value).join(''); }

            function setError(msg) {
                errEl.textContent = msg;
                errEl.style.display = 'block';
                inputs.forEach(i => i.classList.add('error-state'));
                setTimeout(() => {
                    inputs.forEach(i => { i.classList.remove('error-state', 'filled'); i.value = ''; });
                    errEl.style.display = 'none';
                    btn.disabled = true;
                    inputs[0].focus();
                }, 1200);
            }

            function submit() {
                if (submitting || getCode().length < 6) return;
                submitting = true;
                btn.classList.add('loading');
                btn.disabled = true;
                errEl.style.display = 'none';
                @this.call('verify').catch(() => {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    submitting = false;
                });
            }

            inputs.forEach((input, i) => {
                input.addEventListener('input', e => {
                    const val = e.target.value.replace(/\D/g, '');
                    e.target.value = val ? val[0] : '';
                    e.target.classList.toggle('filled', !!e.target.value);
                    if (val && i < 5) inputs[i + 1].focus();
                    otpValue = getCode();
                    btn.disabled = otpValue.length < 6;
                    @this.set('code', otpValue);
                    if (otpValue.length === 6) submit();
                });

                input.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !input.value && i > 0) {
                        inputs[i - 1].value = '';
                        inputs[i - 1].classList.remove('filled');
                        inputs[i - 1].focus();
                        otpValue = getCode();
                        btn.disabled = otpValue.length < 6;
                    }
                    if (e.key === 'Enter' && getCode().length === 6) submit();
                });

                input.addEventListener('paste', e => {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    pasted.split('').forEach((char, j) => {
                        if (inputs[j]) { inputs[j].value = char; inputs[j].classList.add('filled'); }
                    });
                    otpValue = getCode();
                    @this.set('code', otpValue);
                    if (pasted.length === 6) { inputs[5].focus(); btn.disabled = false; submit(); }
                });
            });

            document.addEventListener('livewire:initialized', () => {
                Livewire.on('mfa-error', (params) => {
                    const msg = Array.isArray(params) ? params[0] : (params.message || 'Invalid code. Please try again.');
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    submitting = false;
                    setError(msg);
                });

                Livewire.on('mfa-success', () => {
                    btn.classList.remove('loading');
                    btn.classList.add('success');
                    btn.innerHTML = '<span class="btn-label">✓ Verified!</span>';
                });
            });
        </script>
    </x-filament::section>
</x-filament-panels::page.simple>
