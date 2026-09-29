</main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function logoutUser() {
    try {
        const res = await fetch('/api/auth/logout.php', { method: 'POST' });
        window.location.href = '/login.php';
    } catch (e) {
        window.location.href = '/login.php';
    }
}

async function getCsrfToken() {
    try {
        const res = await fetch('/api/auth/csrf-token.php');
        const json = await res.json();
        return json.csrf_token;
    } catch(e) {
        return '';
    }
}
</script>
</body>
</html>
