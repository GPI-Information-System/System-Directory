


system-directory/
│
├── assets/
│   ├── css/
│   │   ├── style.css              # Dashboard & Login UI codes
│   │   ├── viewer.css             # Viewer page UI code
│   │   ├── analytics.css          # Analytics page styles
│   │   └── notifications.css      # PHASE 3: Notification bell widget styles
│   │
│   └── js/
│       ├── main.js                # Log in & admin JavaScript functionality
│       ├── viewer.js              # Viewer JavaScript functionality
│       ├── analytics.js           # Charts, reports, PDF export, edit note
│       └── notifications.js       # Bell widget, recent updates, auto-refresh
│
├── backend/
│   ├── add_system.php             # Add systems (Super Admin only)
│   ├── edit_system.php            # Edit systems + log changes + email alerts
│   ├── delete_system.php          # Delete systems (Super Admin only)
│   ├── logout.php                 # User logout
│   ├── get_analytics_data.php     # API for charts, uptime, reports
│   ├── update_log_note.php        # Edit notes on existing status logs
│   ├── get_notifications.php      # API for recent status changes (bell widget)
│   └── send_email_notification.php # Email function for critical status alerts
│
├── config/
│   ├── database.php               # Database connection (XAMPP/phpMyAdmin)
│   ├── session.php                # Session & role management
│   └── email_config.php           # SMTP settings for email notifications
│
├── pages/
│   ├── dashboard.php              # Super Admin & Admin dashboard page
│   ├── analytics.php              # Analytics & reporting dashboard
│   └── viewer.php                 # Employee/viewer page (public access)
│
├── uploads/
│   └── logos/                     # System logo uploads storage
│
├── index.php                      # Login page (main entry point)
├── database_setup.sql             # Database creation script
├── README.md                      # Complete installation guide
├── QUICKSTART.md                  # 5-minute quick start guide
└── system_directory_db.sql        # Full database export with sample data





















Demo Accounts
Super Admin: superadmin / admin123

Admin: admin / admin123








 Security Reminder:
The default password admin123 is easy to remember but not secure. When you're ready to use this in production:

Go to phpMyAdmin
Click on system_directory_db → users
Edit each user
Generate a new strong password using: 
http://localhost:8080/system-di4rectory/generate_password.php
Update the password field with the new hash.





system-directory/
│
├── assets/
│   ├── css/
│   │   ├── style.css           -- Dashboard & Login UI codes
│   │   ├── viewer.css          -- Viewer page UI code
│   │   ├── analytics.css      -- Analytics pagestyles
│   │   └── notifications.css   -- Notification bell css
│   │
│   └── js/
│       ├── main.js   -- Log in & admin JavaScript functionality
│       ├── viewer.js  -- Viewer JavaScript functionality
│       ├── analytics.js -- Charts, reports, export, edit note
│       └── notifications.js -- recent updates (system status)
│
├── backend/
│   ├── add_system.php -- Add systems (Super Admin only)
│   ├── edit_system.php -- Edit function (SuperAdmin & admin)
│   ├── delete_system.php -- Delete function (Super Admin only)
│   ├── logout.php    -- User (direct to login page)
│   ├── get_analytics_data.php  -- API for charts,uptime,reports
│   ├── update_log_note.php --Edit notes on existing status logs
│   ├── get_notifications.php -- API for recent status changes 
│   └── send_email_notification.php -- Email for critical status
│
├── config/
│   ├── database.php  --Database connection (XAMPP/phpMyAdmin)
│   ├── session.php      --Session & role management
│   └── email_config.php --SMTP settings for email notifications
│
├── pages/
│   ├── dashboard.php    -- Super Admin & Admin dashboard page
│   ├── analytics.php    -- Analytics & reporting dashboard
│   └── viewer.php       -- Employee/viewer page (public access)
│
├── uploads/
│   └── logos/           -- System logo uploads storage
│
│   
│
├── index.php                      -- Login page (main entry point)
├── database_setup.sql             -- Database creation script
├── README.md                      -- Complete installation guide
├── QUICKSTART.md                  -- 5-minute quick start guide
└── system_directory_db.sql        -- Full database export with sample data