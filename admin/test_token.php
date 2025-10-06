<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Test Token - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Test Token</div>
        </header>
        <main class="container">
            <div class="card">
                <h3>Token Information</h3>
                <div id="tokenInfo"></div>
                <button class="btn btn-primary" onclick="testAPI()">Test API Call</button>
                <div id="apiResult"></div>
            </div>
        </main>
    </div>

<script>
function displayTokenInfo() {
    const token = AdminAPI.getToken();
    const tokenInfo = document.getElementById('tokenInfo');
    
    tokenInfo.innerHTML = `
        <p><strong>Token:</strong> ${token ? token.substring(0, 50) + '...' : 'No token'}</p>
        <p><strong>Token Length:</strong> ${token ? token.length : 0}</p>
        <p><strong>Session Storage:</strong> ${sessionStorage.getItem('admin_token') ? 'Present' : 'Missing'}</p>
        <p><strong>Window Token:</strong> ${window.ADMIN_TOKEN ? 'Present' : 'Missing'}</p>
    `;
}

async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<p>Testing API...</p>';
    
    try {
        // Test health endpoint first (no auth required)
        const healthRes = await fetch('../api/health');
        const healthData = await healthRes.json();
        
        resultDiv.innerHTML = `
            <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <strong>Health Check Success!</strong><br>
                <pre>${JSON.stringify(healthData, null, 2)}</pre>
            </div>
        `;
        
        // Test authenticated endpoint
        const token = AdminAPI.getToken();
        if (token) {
            const authRes = await AdminAPI.request('/admin/staff');
            resultDiv.innerHTML += `
                <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <strong>Auth Test Success!</strong><br>
                    <pre>${JSON.stringify(authRes, null, 2)}</pre>
                </div>
            `;
        } else {
            resultDiv.innerHTML += `
                <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <strong>No Token Available</strong><br>
                    Cannot test authenticated endpoints
                </div>
            `;
        }
    } catch(e) {
        resultDiv.innerHTML = `
            <div style="background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <strong>Error:</strong><br>
                ${e.message}
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    displayTokenInfo();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
