<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password with Code</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 420px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #2563eb; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
        .password-rules { list-style: none; padding: 0; margin-top: 0.5rem; font-size: 0.8rem; }
        .password-rules li { margin-bottom: 0.25rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Enter Verification Code</h2>

        <form method="POST" action="{{ route('password.code.verify.submit') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ $email ?? old('email') }}" required readonly>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="code">6-Digit Verification Code</label>
                <input 
                    type="text" 
                    id="code" 
                    name="code" 
                    inputmode="numeric" 
                    pattern="[0-9]*" 
                    minlength="6" 
                    maxlength="6" 
                    required 
                    autofocus
                >
                @error('code') <div class="error">{{ code }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required>
                
                <!-- Real-time rule checklist with unique IDs -->
                <ul class="password-rules">
                    <li id="rule-length" style="color: #dc2626;">❌ At least 16 characters long</li>
                    <li id="rule-capital" style="color: #dc2626;">❌ At least 1 capital letter (A-Z)</li>
                    <li id="rule-small" style="color: #dc2626;">❌ At least 1 small letter (a-z)</li>
                    <li id="rule-number" style="color: #dc2626;">❌ At least 1 number (0-9)</li>
                    <li id="rule-special" style="color: #dc2626;">❌ At least 1 special character (- _ + = ! @ # $ % ^ & * { } [ ] / " ' :)</li>
                </ul>

                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        if (!passwordInput) return;

        const ruleLength = document.getElementById('rule-length');
        const ruleCapital = document.getElementById('rule-capital');
        const ruleSmall = document.getElementById('rule-small');
        const ruleNumber = document.getElementById('rule-number');
        const ruleSpecial = document.getElementById('rule-special');

        const specialCharsRegex = /[\(\-\_\+\=\!\@\#\$\%\^\&\*\{\}\[\]\\\/\"\'\:\;]/;

        passwordInput.addEventListener('input', function () {
            const val = passwordInput.value;

            // 1. Check Length
            if (val.length >= 16) {
                ruleLength.style.color = '#16a34a';
                ruleLength.textContent = '✔️ At least 16 characters long';
            } else {
                ruleLength.style.color = '#dc2626';
                ruleLength.textContent = '❌ At least 16 characters long';
            }

            // 2. Check Capital Letter
            if (/[A-Z]/.test(val)) {
                ruleCapital.style.color = '#16a34a';
                ruleCapital.textContent = '✔️ At least 1 capital letter (A-Z)';
            } else {
                ruleCapital.style.color = '#dc2626';
                ruleCapital.textContent = '❌ At least 1 capital letter (A-Z)';
            }

            // 3. Check Small Letter
            if (/[a-z]/.test(val)) {
                ruleSmall.style.color = '#16a34a';
                ruleSmall.textContent = '✔️ At least 1 small letter (a-z)';
            } else {
                ruleSmall.style.color = '#dc2626';
                ruleSmall.textContent = '❌ At least 1 small letter (a-z)';
            }

            // 4. Check Number
            if (/[0-9]/.test(val)) {
                ruleNumber.style.color = '#16a34a';
                ruleNumber.textContent = '✔️ At least 1 number (0-9)';
            } else {
                ruleNumber.style.color = '#dc2626';
                ruleNumber.textContent = '❌ At least 1 number (0-9)';
            }

            // 5. Check Special Character
            if (specialCharsRegex.test(val)) {
                ruleSpecial.style.color = '#16a34a';
                ruleSpecial.textContent = '✔️ At least 1 special character (- _ + = ! @ # $ % ^ & * { } [ ] / " \' :)';
            } else {
                ruleSpecial.style.color = '#dc2626';
                ruleSpecial.textContent = '❌ At least 1 special character (- _ + = ! @ # $ % ^ & * { } [ ] / " \' :)';
            }
        });
    });
    </script>
</body>
</html>