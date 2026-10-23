<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password with Code</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #2563eb; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
        .success { color: #16a34a; margin-bottom: 1rem; }
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
    @error('code') <div class="error">{{ $message }}</div> @enderror
</div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>
</html>