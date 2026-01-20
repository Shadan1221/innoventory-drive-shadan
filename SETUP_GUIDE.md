# Innoventory Setup Guide

## Prerequisites
- XAMPP (or WAMP/MAMP) installed
- PHP 7.4 or higher
- MySQL/MariaDB

## Step 1: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Start **Apache** (click "Start" button)
3. Start **MySQL** (click "Start" button)

## Step 2: Place Project Files

1. Copy your project folder to:
   - **Windows**: `C:\xampp\htdocs\innoventory\`
   - **Mac**: `/Applications/XAMPP/htdocs/innoventory/`
   - **Linux**: `/opt/lampp/htdocs/innoventory/`

   Your folder structure should look like:
   ```
   htdocs/innoventory/
   ├── css/
   ├── pkg/
   ├── config.php
   ├── index.php
   ├── session.php
   └── session_check.php
   ```

## Step 3: Create Database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click on **"New"** in the left sidebar
3. Database name: `innoventory_db`
4. Collation: `utf8mb4_general_ci`
5. Click **"Create"**

## Step 4: Create Users Table

1. In phpMyAdmin, select the `innoventory_db` database
2. Click on the **"SQL"** tab
3. Paste and execute this SQL:

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Step 5: Create First Admin User

You need to create your first admin user manually since there's no public admin registration.

### Option A: Using PHP Script (Recommended)

1. Create a temporary file `create_admin.php` in your project root:

```php
<?php
require_once "config.php";

$name = "Super Admin";
$email = "admin@innoventory.com";
$password = "admin123"; // Change this!
$role = "admin";
$status = "approved";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $status);

if ($stmt->execute()) {
    echo "Admin user created successfully!<br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
    echo "<strong>DELETE THIS FILE AFTER USE!</strong>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
?>
```

2. Open in browser: `http://localhost/innoventory/create_admin.php`
3. **IMPORTANT**: Delete `create_admin.php` after creating the admin!

### Option B: Using phpMyAdmin SQL

1. Go to phpMyAdmin → `innoventory_db` → SQL tab
2. Generate password hash first by running this in a PHP file:
   ```php
   <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>
   ```
3. Copy the hash and use in this SQL:

```sql
INSERT INTO users (name, email, password, role, status) 
VALUES (
    'Super Admin', 
    'admin@innoventory.com', 
    '$2y$10$YOUR_HASH_HERE', 
    'admin', 
    'approved'
);
```

## Step 6: Access the Application

1. Open your browser
2. Go to: `http://localhost/innoventory/`
3. You should see the login page

## Step 7: Test the Application

### Test Login Flow:
1. **Login as Admin**:
   - Email: `admin@innoventory.com`
   - Password: `admin123` (or whatever you set)
   - Should redirect to Admin Dashboard

2. **Request Access (New User)**:
   - Click "Request Access" on login page
   - Fill in the form (Name, Email, Password)
   - Submit → Should show "Request submitted successfully!"

3. **Approve User**:
   - Login as admin
   - Go to Admin Dashboard
   - You should see pending user requests
   - Click "Approve" on a user

4. **Login as Approved User**:
   - Logout from admin
   - Login with the approved user credentials
   - Should redirect to User Dashboard

### Test Session Check:
- Visit: `http://localhost/innoventory/session_check.php`
- This shows your current session information

## Troubleshooting

### Database Connection Error
- Check if MySQL is running in XAMPP
- Verify database name in `config.php` matches your database
- Check username/password in `config.php`

### Page Not Found (404)
- Verify files are in `htdocs/innoventory/` folder
- Check Apache is running
- Try: `http://localhost/innoventory/index.php`

### Session Issues
- Check `session_check.php` to see session status
- Ensure PHP sessions are enabled (usually enabled by default)
- Clear browser cookies if having issues

### Permission Denied
- Make sure you're logged in
- Check user status is "approved" in database
- Verify role is set correctly

## Default Credentials (After Setup)

**Admin:**
- Email: `admin@innoventory.com`
- Password: `admin123` (or what you set in create_admin.php)

**⚠️ IMPORTANT: Change the admin password after first login!**

## File Structure Check

Make sure you have these files:
```
innoventory/
├── css/
│   └── main.css
├── pkg/
│   └── user-management/
│       ├── admin_dashboard.php
│       ├── user_dashboard.php
│       ├── register.php
│       ├── logout.php
│       ├── approve.php
│       └── deny.php
├── config.php
├── index.php
├── session.php
└── session_check.php
```

## Next Steps After Setup

1. ✅ Test login as admin
2. ✅ Test user registration
3. ✅ Test approval workflow
4. ✅ Change admin password
5. ✅ Customize styling if needed
6. ✅ Add more features as needed

---

**Need Help?** Check `session_check.php` to debug session issues.

