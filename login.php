<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TEIPI EXAMS - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 text-gray-900">

  <div class="w-full max-w-4xl mx-4">
    <div class="bg-white/95 rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2">

      <!-- Left: Illustration / Branding -->
      <div class="hidden md:flex flex-col items-center justify-center p-10 bg-gradient-to-b from-indigo-600 to-purple-700 text-white">
        <div class="flex items-center gap-3 mb-4">
          <!-- Simple logo -->
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shadow-lg rounded-full bg-white/10 p-1">
            <path d="M12 2L15 8H9L12 2Z" fill="white" opacity=".9"/>
            <path d="M12 22L9 16H15L12 22Z" fill="white" opacity=".9"/>
            <circle cx="12" cy="12" r="3" fill="white"/>
          </svg>
          <div>
            <h2 class="text-2xl font-bold">TEIPI EXAMS</h2>
            <p class="text-sm opacity-90">Secure online testing platform</p>
          </div>
        </div>

        <div class="max-w-xs text-sm leading-relaxed opacity-95">
          <p class="mb-2">Take your exams with confidence. Timed tests, instant feedback, and secure submission.</p>
          <p class="text-xs opacity-80">If you’re an administrator, use the admin portal links on the main site.</p>
        </div>
      </div>

      <!-- Right: Login Form -->
      <div class="p-8 md:p-12">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h3 class="text-2xl font-semibold text-slate-700">Welcome back</h3>
            <p class="text-sm text-slate-500">Sign in to continue to your dashboard</p>
          </div>
          <div class="text-xs text-slate-400">&copy; 2025</div>
        </div>

        <form method="POST" id="loginForm" novalidate>
          <div class="space-y-4">
            <div>
              <label for="username" class="block text-sm font-medium text-slate-600">Username</label>
              <input type="text" id="username" name="username" autocomplete="username" placeholder="johnmark123"
                class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" required aria-required="true" />
            </div>

            <div>
              <label for="password" class="block text-sm font-medium text-slate-600">Password</label>
              <div class="relative mt-1">
                <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password"
                  class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2 pr-32 text-slate-700 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" required aria-required="true" />

                <button type="button" id="togglePassword" aria-label="Show password"
                  class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs bg-slate-100 text-slate-600 hover:bg-slate-200">
                  Show
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" id="remember" name="remember" class="rounded text-indigo-600 focus:ring-indigo-500" />
                Remember me
              </label>
              <a href="#" class="text-sm text-indigo-600 hover:underline">Forgot password?</a>
            </div>

            <!-- Error / Info -->
            <div id="errorBox" class="hidden rounded-lg border border-red-200 bg-red-50/90 text-red-700 px-4 py-2 text-sm flex items-start gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.681-1.36 3.446 0l5.518 9.82c.75 1.334-.213 2.98-1.723 2.98H4.462c-1.51 0-2.472-1.646-1.723-2.98l5.518-9.82zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-.993.883L9 7v4a1 1 0 001.993.117L11 11V7a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <div id="errorText" class="flex-1"></div>
              <button type="button" id="dismissError" class="text-red-700/80 hover:text-red-900 px-1">✕</button>
            </div>

            <div>
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold px-4 py-2 shadow hover:opacity-95 transition">
                Sign in
              </button>
            </div>

            <div class="pt-2 text-center text-sm text-slate-500">
              Don’t have an account? <a href="#" class="text-indigo-600 font-medium hover:underline">Register</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script>
    $(function () {
      $('#username').focus();

      // Toggle password visibility
      $('#togglePassword').on('click', function () {
        const pw = $('#password');
        const type = pw.attr('type') === 'password' ? 'text' : 'password';
        pw.attr('type', type);
        $(this).text(type === 'password' ? 'Show' : 'Hide');
        $(this).attr('aria-label', type === 'password' ? 'Show password' : 'Hide password');
      });

      // Dismiss error
      $('#dismissError').on('click', function () {
        $('#errorBox').hide();
      });

      $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const username = $('#username').val().trim();
        const password = $('#password').val().trim();

        if (!username || !password) {
          $('#errorText').text('Please enter both username and password.');
          $('#errorBox').show();
          return;
        }

        const data = $(this).serializeArray();
        data.push({ name: 'action', value: 'login' });

        $.ajax({
          type: 'POST',
          url: 'exec/fetch.php',
          data: data,
          dataType: 'JSON'
        }).done(function (response) {
          // Normalize response if server returns numeric codes or JSON
          const code = typeof response === 'string' || typeof response === 'number' ? String(response) : (response && response.status ? String(response.status) : null);

          if (code === '1') {
            window.location.href = 'index.php';
            return;
          }

          if (code === '2') {
            $('#errorText').text('Invalid username or password.');
          } else if (code === '3' || code === '4') {
            $('#errorText').text("You don't have an account. Please register first.");
          } else {
            $('#errorText').text('Unexpected error occurred. Please try again later.');
          }

          $('#errorBox').show();
        }).fail(function () {
          $('#errorText').text('Server error. Please try again later.');
          $('#errorBox').show();
        });
      });
    });
  </script>
</body>
</html>
