<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_admin()) {
        redirect(SITE_URL . 'admin/dashboard.php');
    } else {
        redirect(SITE_URL . 'student/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Home</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Top Navigation Bar */
        .top-nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .nav-logo img {
            height: 50px;
            width: 50px;
            object-fit: contain;
        }
        
        .nav-logo-text {
            color: var(--wmsu-maroon);
            font-size: 18px;
            font-weight: 700;
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-menu a {
            color: var(--wmsu-gray-dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-menu a:hover {
            color: var(--wmsu-maroon);
        }
        
        .nav-buttons {
            display: flex;
            gap: 15px;
        }
        
        .nav-btn {
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .nav-btn-login {
            color: var(--wmsu-maroon);
            border: 2px solid var(--wmsu-maroon);
            background: transparent;
        }
        
        .nav-btn-login:hover {
            background: var(--wmsu-maroon);
            color: white;
        }
        
        .nav-btn-register {
            background: var(--wmsu-maroon);
            color: white;
            border: 2px solid var(--wmsu-maroon);
        }
        
        .nav-btn-register:hover {
            background: var(--wmsu-maroon-dark);
            border-color: var(--wmsu-maroon-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--wmsu-maroon) 0%, var(--wmsu-maroon-dark) 100%);
            color: white;
            padding: 100px 20px;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .hero-content p {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.95;
            line-height: 1.6;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-btn {
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .hero-btn-primary {
            background: white;
            color: var(--wmsu-maroon);
        }
        
        .hero-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .hero-btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .hero-btn-secondary:hover {
            background: white;
            color: var(--wmsu-maroon);
        }
        
        /* Features Section */
        .features-section {
            padding: 80px 20px;
            background: white;
        }
        
        .section-title {
            text-align: center;
            font-size: 36px;
            color: var(--wmsu-maroon);
            margin-bottom: 50px;
            font-weight: 700;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            text-align: center;
            padding: 40px 30px;
            border-radius: 15px;
            background: var(--wmsu-gray);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(128, 0, 0, 0.15);
            border-color: var(--wmsu-maroon);
        }
        
        .feature-icon {
            font-size: 56px;
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            color: var(--wmsu-maroon);
            margin-bottom: 15px;
            font-size: 22px;
            font-weight: 600;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.6;
        }
        
        /* Info Section */
        .info-section {
            background: var(--wmsu-gray);
            padding: 80px 20px;
        }
        
        .info-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        
        .info-content h2 {
            color: var(--wmsu-maroon);
            font-size: 36px;
            margin-bottom: 25px;
            font-weight: 700;
        }
        
        .info-content p {
            font-size: 18px;
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        .info-highlight {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-highlight p {
            margin-bottom: 10px;
        }
        
        /* Footer */
        footer {
            background: var(--wmsu-maroon-dark);
            color: var(--wmsu-white);
            text-align: center;
            padding: 30px 20px;
            margin-top: 0;
        }
        
        footer p {
            margin-bottom: 5px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .hero-content h1 {
                font-size: 32px;
            }
            
            .hero-content p {
                font-size: 16px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-btn {
                width: 100%;
                max-width: 300px;
            }
            
            .section-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="top-nav-content">
            <a href="index.php" class="nav-logo">
                <img src="images/wmsu.png" alt="WMSU Logo">
                <span class="nav-logo-text">WMSU Bus Reserve</span>
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
            </ul>
            
            <div class="nav-buttons">
                <a href="login.php" class="nav-btn nav-btn-login">Login</a>
                <a href="register.php" class="nav-btn nav-btn-register">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>🚍 WMSU Bus Reserve System</h1>
            <p>Book your transportation easily and efficiently. Reserve buses for official trips, field work, and academic activities.</p>
            <div class="hero-buttons">
                <a href="login.php" class="hero-btn hero-btn-primary">Get Started</a>
                <a href="register.php" class="hero-btn hero-btn-secondary">Create Account</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <h2 class="section-title">Why Choose Our System?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3>Easy Booking</h3>
                <p>Reserve buses with just a few clicks. View available dates and choose your preferred schedule.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🚌</div>
                <h3>Real-time Availability</h3>
                <p>Check bus and driver availability instantly. No more double bookings or conflicts.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3>Quick Approval</h3>
                <p>Get your reservations approved efficiently. Receive email notifications for updates.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Track Reservations</h3>
                <p>Monitor your booking status and history. View all your past and upcoming trips.</p>
            </div>
        </div>
    </section>

    <!-- Info Section -->
    <section class="info-section" id="about">
        <div class="info-content">
            <h2>About WMSU Bus Service</h2>
            <p>Western Mindanao State University provides bus transportation services for students, faculty, and staff for official university activities. Our online reservation system makes it easier to book and manage your transportation needs.</p>
            
            <div class="info-highlight">
                <p><strong>📍 Location:</strong> Normal Road, Baliwasan, Zamboanga City</p>
                <p><strong>🕐 Operating Hours:</strong> Monday to Saturday, 7:00 AM - 5:00 PM</p>
                <p><strong>🚌 Available Buses:</strong> 3 units with professional drivers</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p><strong>&copy; <?php echo date('Y'); ?> Western Mindanao State University</strong></p>
        <p>Normal Road, Baliwasan, Zamboanga City, Philippines 7000</p>
        <p style="margin-top: 10px; opacity: 0.8;">All rights reserved</p>
    </footer>
</body>
</html>