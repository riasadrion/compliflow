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
                background: rgba(220,38,38,0.08);
                animation: otp-shake 0.4s ease;
            }
            {{ '@' }}keyframes otp-shake {
                0%, 100% { transform: translateX(0); }
                20%  { transform: translateX(-5px); }
                60%  { transform: translateX(5px); }
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
                transition: background 0.2s, opacity 0.15s;
                margin-bottom: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            .otp-verify-btn:disabled { opacity: 0.45; cursor: not-allowed; }
            .otp-verify-btn:hover:not(:disabled) { opacity: 0.88; }
            .otp-verify-btn.success { background: #16a34a; }
            .otp-verify-btn.loading { opacity: 0.75; cursor: not-allowed; }
            .otp-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255,255,255,0.4);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin 0.6s linear infinite;
                display: none;
            }
            .otp-verify-btn.loading .otp-spinner { display: block; }
            .otp-verify-btn.loading .btn-label { display: none; }
            {{ '@' }}keyframes spin { to { transform: rotate(360deg); } }
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
                display: none;
            }
        </style>

        <div class="otp-grid" id="otp-wrapper">
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code" autofocus class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
            <input type="text" maxlength="1" inputmode="numeric" pattern="\d" class="otp-input" />
        </div>

        <p class="otp-error" id="otp-error"></p>

        <button class="otp-verify-btn" id="verify-btn" disabled>
            <span class="otp-spinner"></span>
            <span class="btn-label">Verify</span>
        </button>


        <script>
            const inputs  = Array.from(document.querySelectorAll('.otp-input'));
            const btn     = document.getElementById('verify-btn');
            const errEl   = document.getElementById('otp-error');
            let otpValue  = '';
            let submitting = false;

            function getCode() {
                return inputs.map(i => i.value).join('');
            }

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

                @this.call('verify').then(() => {
                    // Success handled by Livewire redirect
                }).catch(() => {
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
