<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        // 1) Thử đăng nhập qua API trước
        try {
            // Build absolute API URL based on current host and project path
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $projectBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); // e.g. /pandabackend
            $apiBase = $scheme . '://' . $host . $projectBase . '/api';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiBase . '/auth/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'username' => $username,
                'password' => $password
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            $apiResp = curl_exec($ch);
            $apiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($apiCode === 200) {
                $apiData = json_decode($apiResp, true);
                if ($apiData && !empty($apiData['success']) && !empty($apiData['data']['token'])) {
                    $_SESSION['admin_token'] = $apiData['data']['token'];
                    // Lưu thông tin user tối thiểu
                    $apiUser = $apiData['data']['user'] ?? ['username' => $username, 'role' => 'admin'];
                    $_SESSION['admin_user'] = $apiUser;
                    header('Location: dashboard.php');
                    exit;
                }
            } else if ($apiCode === 401) {
                $apiData = json_decode($apiResp, true);
                $error = $apiData['message'] ?? 'Tên đăng nhập hoặc mật khẩu không đúng.';
                // Ngừng tại đây, không fallback DB nữa để tránh lỗi mơ hồ
                throw new Exception($error);
            } else if ($apiCode > 0) {
                $apiData = json_decode($apiResp, true);
                $error = $apiData['message'] ?? ('Lỗi đăng nhập API (HTTP ' . $apiCode . ')');
                throw new Exception($error);
            }
        } catch (Exception $e) {
            // Nếu API báo lỗi rõ ràng, hiển thị cho người dùng
            if (!$error) {
                $error = $e->getMessage() ?: 'Lỗi hệ thống. Vui lòng thử lại sau.';
            }
            // Bỏ qua fallback DB để tránh hiểu nhầm nguồn lỗi
        }

        // Nếu đến đây và chưa có $error, đặt thông báo mặc định
        if (!$error) {
            $error = 'Không thể đăng nhập. Vui lòng kiểm tra lại tài khoản/mật khẩu hoặc kết nối tới API.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Panda Admin</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { display: grid; place-items: center; min-height: 100vh; }
        .card { max-width: 420px; width: 100%; }
        .error { color: #ef4444; margin-top: 8px; }
    </style>
  </head>
<body>
    <div class="card">
        <h1 class="title">Panda Admin</h1>
        <p class="subtitle">Đăng nhập hệ thống</p>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="form">
            <div class="form-group">
                <label for="username">Tài khoản</label>
                <input id="username" name="username" type="text" required />
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required />
            </div>
            <button type="submit" class="btn primary full">Đăng nhập</button>
        </form>
    </div>
    <script>
      console.log('[Login] Page loaded');
    </script>
    <script src="js/app.js"></script>
    <script src="js/api.js"></script>
</body>
</html>


