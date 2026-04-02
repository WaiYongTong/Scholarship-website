<style>
    .main-footer {
        background: #151521;
        color: #ffffff;
        padding: 80px 0 0; 
        margin-top: 0;
        font-family: 'Inter', sans-serif;
    }

    .footer-container {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        gap: 60px;
        padding: 0 20px;
    }

    .footer-col { flex: 1; }

    .footer-col h3 {
        color: #009ef7;
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 25px;
    }

    .footer-col p {
        color: #a1a5b7;
        font-size: 14px;
        line-height: 1.6;
    }

    .footer-links { list-style: none; padding: 0; }
    .footer-links li { margin-bottom: 12px; }
    .footer-links a {
        color: #a1a5b7;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .footer-links a:hover {
        color: #009ef7;
        text-decoration: underline;
    }

    .footer-bottom {
        max-width: 1100px;
        margin: 60px auto 0;
        padding: 30px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        color: #7e8299;
        font-size: 13px;
    }
</style>

<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-col">
            <h3>Scholarship Portal</h3>
            <p>Empowering students through digital transformation and transparent merit-based financial aid.</p>
        </div>

        <div class="footer-col">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="#about-section">About</a></li>
                
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
                        <a href="student/dashboard.php">Profile</a>
                <?php else: ?>
                    <li><a href="register.php">Apply Now</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Contact Info</h3>
            <p>
                <strong>Email:</strong> help@scholarship.edu.my<br>
                <strong>Office:</strong> Admin Block, Level 2<br>
                <strong>Support:</strong> Mon - Fri, 9AM - 5PM
            </p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; 2025 Digital Scholarship Application and Tracking System</p>
        <p>Web Version 1.0</p>
    </div>
</footer>

</body>
</html>