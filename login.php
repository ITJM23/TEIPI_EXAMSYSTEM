<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TEIPI EXAMS - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 via-indigo-800 to-purple-900 text-white font-sans">

  <!-- Container -->
  <div class="w-full max-w-md p-6">
    <div class="bg-white text-gray-800 rounded-2xl shadow-2xl overflow-hidden transform hover:scale-[1.02] transition duration-300">

      <!-- Header -->
      <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-center">
        <h1 class="text-2xl font-bold tracking-wide">TEIPI EXAMS</h1>
        <p class="text-sm text-gray-200">Login to start your exam</p>
      </div>

      <!-- Form -->
      <div class="p-8">
        <form method="POST" id="loginForm">
          <!-- Email -->
          <div class="mb-4">
            <label for="username" class="block mb-1 font-medium text-gray-700">Email</label>
            <input type="text" placeholder="username" id="username" name="username"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>

          <!-- Password -->
          <div class="mb-6">
            <label for="password" class="block mb-1 font-medium text-gray-700">Password</label>
            <input type="password" placeholder="••••••••" id="password" name="password"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>

          <!-- Error Box -->
          <div id="errorBox" class="hidden mb-4 p-3 rounded-lg bg-red-100 text-red-700 text-sm font-medium"></div>

          <!-- Button -->
          <button type="submit"
            class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold py-2 rounded-xl shadow-md hover:opacity-90 transition">
            Login
          </button>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-sm text-center text-gray-500">
          <p>Forgot your password? <a href="#" class="text-indigo-600 hover:underline">Reset</a></p>
          <p class="mt-2">Don’t have an account? <a href="#" class="text-purple-600 hover:underline">Register</a></p>
        </div>
      </div>
    </div>

    <!-- Branding -->
    <p class="mt-6 text-center text-gray-300 text-xs">
      &copy; 2025 TEIPI EXAMS. All rights reserved.
    </p>
  </div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

  <script>
    $(document).ready(function () {
      $('#username').focus();

      $('#loginForm').on('submit', function (aa) {
        aa.preventDefault();

        var data = $('#loginForm').serializeArray();
        data.push({ name: 'action', value: 'login' });

        $.ajax({
          type: "POST",
          url: "exec/fetch.php",
          data: data,
          dataType: "JSON",
          success: function (response) {
            const errorBox = $('#errorBox');

            if (response == '1') {
              location.href = 'index.php';
            } 
            else if (response == '2') {
              errorBox.hide().removeClass('hidden').text('Invalid username or password. Please try again.').fadeIn();
            } 
            else if (response == '3' || response == '4') {
              errorBox.hide().removeClass('hidden').text('You don\'t have an account. Please register first.').fadeIn();
            } 
            else {
              errorBox.hide().removeClass('hidden').text('Unexpected error occurred.').fadeIn();
            }
          }
        });
      });
    });
  </script>
</body>
</html>
