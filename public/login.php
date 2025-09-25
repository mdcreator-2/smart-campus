<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <title>Login | Smart Campus</title>
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
    }

    .login-box {
      background: #fff;
      padding: 2rem 2.5rem;
      border-radius: 1rem;
      box-shadow: 0 0.8rem 1rem rgba(15, 25, 231, 0.1);
      width: 100%;
      max-width: 400px;
    }
  </style>
</head>

<body>
  <div class="login-box mx-auto">
    <h2 class="mb-4 text-center">Login</h2>
    <form method="post" action="">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <div class="mt-3 text-center">
      <a href="signup.php">Don't have an account? Sign up</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>