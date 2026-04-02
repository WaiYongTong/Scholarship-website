<?php include('includes/header.php'); ?>

<style>
    html { scroll-behavior: smooth; }
    body { overflow-x: hidden; }

    .hero {
        height: 80vh;
        background: linear-gradient(rgba(30, 30, 45, 0.85), rgba(0, 158, 247, 0.5)), url('homepage.jpeg');
        background-size: cover;
        background-position: center;
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-align: center; color: white;
    }
    
    .hero h1 { font-size: 50px; margin-bottom: 20px; text-shadow: 2px 2px 10px rgba(0,0,0,0.5); font-weight: 800; }
    
    .hero-btns .btn {
        padding: 15px 35px;
        margin: 10px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        transition: 0.3s ease;
    }
    .btn-blue { background: #009ef7; color: white; }
    .btn-white { background: #ffffff; color: #1e1e2d; }
    .hero-btns .btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }

    .features-section, .about-container {
        background: #f8fbff; 
    }

    .features-section {
        padding: 0 20px 20px;
        position: relative;
    }

    .feature-grid {
        display: flex;
        justify-content: center;
        gap: 30px;
        max-width: 1200px;
        margin: -90px auto 0; 
        z-index: 10;
        position: relative;
    }

    .feature-item {
        flex: 1;
        padding: 40px 30px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 158, 247, 0.08);
        transition: all 0.3s ease;
    }

    .feature-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0, 158, 247, 0.12);
        border-color: #009ef7;
    }

    .feature-item h4 {
        color: #1e1e2d;
        border-left: 5px solid #009ef7;
        padding-left: 15px;
        font-weight: 800;
        font-size: 22px;
        margin-bottom: 15px;
    }

    .about-container {
        max-width: 1100px; 
        margin: 0 auto !important;
        padding: 60px 20px 100px !important; 
        background: transparent !important;
    }

    .section-title {
        color: #1e1e2d;
        margin-bottom: 30px;
        border-bottom: 4px solid #009ef7;
        display: inline-block;
        padding-bottom: 10px;
        font-weight: 800;
        font-size: 36px;
        letter-spacing: -0.8px;
    }

    .mission-vision { 
        display: flex; 
        gap: 40px; 
        margin: 40px 0; 
    }

    .mv-card {
        flex: 1;
        padding: 40px;
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.03);
        border: 1px solid #eff2f5;
    }
    .mv-card h3 { color: #009ef7; font-weight: 800; margin-bottom: 15px; }

    .roles-list {
        list-style: none;
        padding-left: 0;
        margin-top: 30px;
    }

    .roles-list li {
        padding: 15px 20px;
        background: #ffffff;
        margin-bottom: 12px;
        border-radius: 10px;
        border-left: 5px solid #009ef7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        color: #4b5563;
        font-size: 16px;
    }

    .stats-bar {
        background: #1e1e2d;
        color: white;
        padding: 60px 0;
        display: flex;
        justify-content: space-around;
        align-items: center;
    }
    .stat-item h2 { font-size: 48px; margin: 0; color: #009ef7; font-weight: 800; }
    .stat-item p { margin: 10px 0 0; opacity: 0.7; font-size: 14px; letter-spacing: 1px; }

</style>

<div class="hero">
    <h1>Empowering Academic Excellence <br> Through Financial Support</h1>
    <div class="hero-btns">
        <a href="register.php" class="btn btn-blue">Apply Now</a>
        <a href="#about-section" class="btn btn-white">Learn More</a>
    </div>
</div>

<div class="features-section">
    <div class="feature-grid">
        <div class="feature-item">
            <h4>Find Schemes</h4>
            <p>Browse through various available scholarship programs tailored for your discipline and academic goals.</p>
        </div>
        <div class="feature-item">
            <h4>Easy Application</h4>
            <p>Submit your documents and application forms through our streamlined, fully digital review process.</p>
        </div>
        <div class="feature-item">
            <h4>Track Status</h4>
            <p>Monitor your application progress in real-time from your personal secure dashboard.</p>
        </div>
    </div>
</div>

<div id="about-section" class="about-container">
    <h2 class="section-title">Institutional Overview</h2>
    <p style="font-size: 18px; color: #3f4254;">
        The <strong>Digital Scholarship Application and Tracking System</strong> is dedicated to the belief that education is the primary driver of national prosperity. Our mission is to provide a standardized, merit-based platform that ensures financial assistance reaches those who demonstrate academic potential and financial need.
    </p>

    <div class="mission-vision">
        <div class="mv-card">
            <h3>Our Mission</h3>
            <p>To digitize and optimize the scholarship lifecycle, from initial application to final disbursement, ensuring total transparency and accountability for all stakeholders involved.</p>
        </div>
        <div class="mv-card">
            <h3>Our Vision</h3>
            <p>To eliminate financial barriers to higher education and serve as a global benchmark for integrity and efficiency in educational funding management.</p>
        </div>
    </div>

    <h2 class="section-title" style="margin-top: 60px !important;">Governance & Roles</h2>
    <p>The integrity of our awards is maintained through a rigorous multi-tier review process involving several key administrative roles:</p>
    
    <ul class="roles-list">
        <li><strong>Students:</strong> Register, browse available schemes, and submit applications via a centralized dashboard.</li>
        <li><strong>Committee:</strong> The final decision-making body that approves scholarship awards based on verified merit.</li>
        <li><strong>Reviewers:</strong> Dedicated experts who evaluate initial eligibility and academic merit of all incoming applications.</li>
        <li><strong>Admins:</strong> Manage system operations, user security, and maintain the accuracy of the scholarship database.</li>
    </ul>
</div>

<div class="stats-bar">
    <div class="stat-item"><h2>$2.5M+</h2><p>Total Funds Allocated</p></div>
    <div class="stat-item"><h2>12,000+</h2><p>Applications Processed</p></div>
    <div class="stat-item"><h2>98%</h2><p>Approval Efficiency</p></div>
</div>

<?php include('includes/footer.php'); ?>