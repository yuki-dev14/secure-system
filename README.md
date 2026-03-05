# CAPSTONE - Secure Authentication System

A professional authentication system built with Laravel and Vue.js, featuring RBAC (Role-Based Access Control), QR verification, and a modern dark-themed interface.

## 🚀 Features

- **Secure Login System** with "Remember Me" functionality
- **User Registration** with role assignment and office location
- **Password Recovery** with reset link validation
- **QR Code Verification** for enhanced security
- **Role-Based Access Control (RBAC)**
- **Professional Dark Theme** with SECURE branding
- **Responsive Design**
- **Email Verification**

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** 8.0 or higher
- **Composer** (PHP dependency manager)
- **Node.js** (v16 or higher) and **npm**
- **PostgreSQL** (v12 or higher)
- **Git**

## 🔧 Installation & Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/yuki-dev14/secure-system.git
cd secure-system
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

If you encounter any errors, try:

```bash
composer update
```

### Step 3: Install Node.js Dependencies

```bash
npm install
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

This will automatically update your `.env` file with a new application key.

### Step 5: Database Setup

The `.env` file is already configured with shared development credentials. The database settings are:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

**Create the PostgreSQL database:**

```sql
-- Open PostgreSQL (pgAdmin or psql terminal)
CREATE DATABASE your_database_name;
```

**Run migrations:**

```bash
php artisan migrate
```

**Optional - Seed sample data:**

```bash
php artisan db:seed
```

### Step 6: Build Frontend Assets

For development:

```bash
npm run dev
```

For production:

```bash
npm run build
```

### Step 7: Start the Development Server

```bash
php artisan serve
```

The application will be available at: `http://localhost:8000`

## 🎯 Project Structure

```
CAPSTONE/
├── app/                    # Laravel application code
│   ├── Http/
│   │   └── Controllers/    # Application controllers
│   └── Models/             # Eloquent models
├── bootstrap/              # Laravel bootstrap files
├── config/                 # Configuration files
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── public/                 # Public assets
├── resources/
│   ├── js/                # JavaScript/Vue files
│   │   ├── Components/    # Reusable components
│   │   ├── Composables/   # Vue composables
│   │   ├── Layouts/       # Page layouts
│   │   └── Pages/         # Page components
│   │       ├── Auth/      # Authentication pages
│   │       ├── Beneficiaries/
│   │       ├── Profile/
│   │       ├── QRCode/
│   │       └── Verification/
│   ├── css/               # Stylesheets
│   └── views/             # Blade templates
├── routes/
│   ├── web.php            # Web routes
│   └── api.php            # API routes
├── storage/               # Application storage
├── tests/                 # Application tests
├── vendor/                # Composer dependencies
├── node_modules/          # NPM dependencies
├── .env                   # Environment configuration (shared for dev)
├── composer.json          # PHP dependencies
├── package.json           # Node dependencies
└── vite.config.js         # Vite configuration
```

## 📱 Authentication Pages

### Login Page (`/login`)

- Professional dark theme with SECURE branding
- Email and password fields
- "Remember Me" checkbox
- "Forgot Password" link
- QR verification option
- "Sign In" button

### Register Page (`/register`)

- Full Name field
- Email field
- System Role dropdown (Admin, User, etc.)
- Office Location field
- Password and Confirm Password fields
- "Register" button
- "Log in" link for existing users

### Forgot Password Page (`/forgot-password`)

- Password reset instructions
- Email field
- "Send Reset Link" button
- "Back to Sign In" link

## 🔄 Team Collaboration Workflow

### When you make changes:

```bash
# Check what files changed
git status

# Add your changes
git add .

# Commit with a descriptive message
git commit -m "Add user profile page functionality"

# Push to GitHub
git push origin main
```

### When teammates make changes:

```bash
# Pull latest changes
git pull origin main

# Install any new dependencies
composer install
npm install

# Run any new migrations
php artisan migrate

# Rebuild frontend assets if needed
npm run dev
```

### Working on Features (Branching):

```bash
# Create a new branch for your feature
git checkout -b feature/user-dashboard

# Make your changes, then:
git add .
git commit -m "Add user dashboard with statistics"
git push origin feature/user-dashboard

# Create a Pull Request on GitHub for team review
```

## 🛠️ Common Development Commands

### Laravel Commands

```bash
# Clear application cache
php artisan cache:clear

# Clear configuration cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Run all clear commands at once
php artisan optimize:clear

# Create a new migration
php artisan make:migration create_users_table

# Create a new controller
php artisan make:controller UserController

# Create a new model
php artisan make:model User

# Run the application
php artisan serve

# Run on a specific port
php artisan serve --port=8080
```

### NPM Commands

```bash
# Install dependencies
npm install

# Run development server (hot reload)
npm run dev

# Build for production
npm run build

# Watch files for changes
npm run watch
```

## 🗄️ Database Management

### Create New Migration

```bash
php artisan make:migration create_table_name
```

### Run Migrations

```bash
# Run all pending migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Reset all migrations
php artisan migrate:reset

# Refresh migrations (drop all tables and re-run)
php artisan migrate:refresh
```

### Seeders

```bash
# Create a seeder
php artisan make:seeder UserSeeder

# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=UserSeeder
```

## 🐛 Troubleshooting

### Issue: "Permission denied" errors

**Windows (PowerShell as Administrator):**

```bash
icacls "storage" /grant Users:F /T
icacls "bootstrap/cache" /grant Users:F /T
```

### Issue: Database connection failed

1. Check if PostgreSQL is running
2. Verify database credentials in `.env`
3. Ensure the database exists:
   ```sql
   CREATE DATABASE your_database_name;
   ```

### Issue: npm/composer dependencies won't install

```bash
# Delete lock files and reinstall
rm composer.lock
rm package-lock.json
composer install
npm install
```

### Issue: Changes not reflecting

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild assets
npm run build
```

## 🔐 Security Notes

**For Development:**

- The `.env` file is shared among the team for development purposes
- All team members use the same database credentials

**⚠️ For Production (Future Deployment):**

- **NEVER** commit `.env` to GitHub
- Use environment-specific configurations
- Enable HTTPS
- Use strong, unique passwords
- Enable two-factor authentication
- Regular security audits

## 📞 Team Communication

### Before Making Major Changes:

1. Discuss with the team
2. Create a new branch
3. Test thoroughly
4. Create a Pull Request for review

### Git Commit Message Guidelines:

- Use present tense: "Add feature" not "Added feature"
- Be descriptive: "Add user role verification to login" not "Fix stuff"
- Reference issues: "Fix login bug #23"

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Git Documentation](https://git-scm.com/doc)

## 👥 Team Members

- [Add team member names here]

## 📝 License

This project is for educational purposes as part of the CAPSTONE project.

## 🆘 Getting Help

If you encounter any issues:

1. Check this README first
2. Search for the error message online
3. Ask in the team group chat
4. Check the Issues tab on GitHub
5. Ask your instructor

---

**Project Repository:** https://github.com/yuki-dev14/secure-system.git

**Last Updated:** March 2026

---

## Quick Start Checklist

- [ ] Clone repository
- [ ] Run `composer install`
- [ ] Run `npm install`
- [ ] Run `php artisan key:generate`
- [ ] Create PostgreSQL database
- [ ] Run `php artisan migrate`
- [ ] Run `npm run dev` (in one terminal)
- [ ] Run `php artisan serve` (in another terminal)
- [ ] Open `http://localhost:8000`
- [ ] Test login page
- [ ] Start coding! 🚀
