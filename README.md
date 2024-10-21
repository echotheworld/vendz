# Vendz Project

## Installation Guide

### Prerequisites

Ensure you have the following installed:
- PHP version 8.1 or newer
- Composer (PHP dependency manager)
- Git (version control system)

Check your PHP version:
```bash
php -v
```

### Step-by-Step Installation

#### 1. Download the Project

```bash
git clone https://github.com/echotheworld/vendz.git
cd vendz
```

#### 2. Install Project Dependencies

```bash
composer install
```

#### 3. Configure Environment Settings

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` with a text editor and update settings.

#### 4. Set Up Firebase

1. Create or select a project in the [Firebase Console](https://console.firebase.google.com/).
2. Download the Firebase credentials JSON file.
3. Place it in your project root and rename to `firebase-config.json`.
4. Add `firebase-config.json` to your `.gitignore`.

#### 5. Start the Application

```bash
php -S localhost:8000
```

#### 6. Access Your Application

Open a web browser and go to: `http://localhost:8000`

### Troubleshooting

- **Composer errors**: Verify Composer installation and PHP version.
- **Firebase issues**: Check `firebase-config.json` and its reference in code.
- **"500 Internal Server Error"**: Check PHP error log:
  ```bash
  php -i | grep "error_log"
  ```
- **Port 8000 in use**: Try a different port:
  ```bash
  php -S localhost:8080
  ```

### Development Workflow

1. Edit code as needed.
2. If `composer.json` changes, run `composer update`.
3. Use Git for version control:
   ```bash
   git add .
   git commit -m "Description of changes"
   ```
4. Restart PHP server to apply changes.

<div class="note">

### Security Notes

- PHP's built-in server is for development only. Use Apache or Nginx for production.
- Keep `.env` and `firebase-config.json` secure and out of version control.
- Regularly update dependencies with `composer update`.

</div>
