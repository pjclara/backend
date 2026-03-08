# 🚀 Production Storage Setup Guide (Hostinger)

## Overview

This guide ensures audio files are served directly from storage without going through Laravel routes.

---

## 🔧 Required Setup Steps

### 1. **Create Symbolic Link**

SSH into your Hostinger server and run:

```bash
cd /path/to/your/public_html  # or /home/username/domains/education.medtrack.click/public_html
ln -sfn ../storage/app/public storage
```

**Verify the symlink:**
```bash
ls -la storage
# Should show: storage -> ../storage/app/public
```

### 2. **Set Correct Permissions**

```bash
# Set storage folder permissions
chmod -R 775 ../storage/app/public
chmod -R 775 storage

# Ensure the web server can read the files
chown -R username:username ../storage/app/public
```

Replace `username` with your actual Hostinger username.

### 3. **Configure `.env` for Production**

Update your production `.env` file:

```env
APP_URL=https://education.medtrack.click
FILESYSTEM_DISK=public
```

**Important:** Remove trailing slashes from `APP_URL`.

### 4. **Verify Storage Structure**

Ensure this directory structure exists:

```
public_html/
├── storage/                          ← symbolic link
│   └── audio/
│       └── sentences/
│           └── exercise-1-a-mae.mp3
│
../storage/app/public/               ← actual storage
    └── audio/
        └── sentences/
            └── exercise-1-a-mae.mp3
```

### 5. **Test Storage Access**

Create a test file and verify access:

```bash
# Create test file
echo "test" > ../storage/app/public/audio/test.txt

# Test URL in browser
https://education.medtrack.click/storage/audio/test.txt
```

You should see "test" in the browser.

---

## 🔍 Troubleshooting

### Issue 1: 404 on Storage Files

**Symptom:** `https://education.medtrack.click/storage/audio/sentences/exercise-1-a-mae.mp3` returns 404

**Solutions:**

1. **Check symlink exists:**
   ```bash
   ls -la public_html/storage
   ```

2. **Recreate symlink if broken:**
   ```bash
   cd public_html
   rm -f storage  # Remove if exists
   ln -sfn ../storage/app/public storage
   ```

3. **Check file exists:**
   ```bash
   ls -la ../storage/app/public/audio/sentences/
   ```

### Issue 2: Files Route to API

**Symptom:** Audio requests like `/api/exercises/exercise-1-a-mae.mp3` return PostgreSQL UUID errors

**Solution:** This is already fixed by:
- Adding UUID constraint to API routes: `->whereUuid('exercise')`
- Updated `.htaccess` to prioritize static files

### Issue 3: Permission Denied

**Symptom:** 403 Forbidden on storage files

**Solution:**
```bash
chmod -R 755 ../storage/app/public/audio
chmod -R 755 public_html/storage
```

### Issue 4: Wrong URLs Generated

**Symptom:** Application generates `/api/exercises/...` instead of `/storage/...`

**Solution:** Ensure you're using `Storage::disk('public')->url()` which respects `APP_URL`:

```php
// ✅ Correct
$url = Storage::disk('public')->url('audio/sentences/exercise-1.mp3');
// Generates: https://education.medtrack.click/storage/audio/sentences/exercise-1.mp3

// ❌ Wrong
$url = '/api/exercises/' . $filename;
```

---

## 🏗️ Server Configuration Differences

### Local Development (Artisan Serve)
- Uses PHP built-in server
- Serves files directly without `.htaccess`
- Routes processed after checking for files

### Production (Hostinger - LiteSpeed/Apache)
- Uses LiteSpeed or Apache web server
- Relies on `.htaccess` for routing rules
- Files checked in order defined in `.htaccess`

### Key Differences:

| Aspect | Local (Artisan) | Production (Hostinger) |
|--------|----------------|------------------------|
| Web Server | PHP Built-in | LiteSpeed/Apache |
| Routing | Native PHP | `.htaccess` rules |
| Static Files | Auto-served | Needs symlink |
| URL Rewriting | Internal | `mod_rewrite` |

---

## ✅ Verification Checklist

After deployment, verify:

- [ ] Symlink exists: `ls -la public_html/storage`
- [ ] Files accessible: `ls -la ../storage/app/public/audio/sentences/`
- [ ] Permissions correct: `755` for directories, `644` for files
- [ ] `.env` has correct `APP_URL` (no trailing slash)
- [ ] Test URL works: `https://education.medtrack.click/storage/audio/test.txt`
- [ ] Audio files load in browser without API errors
- [ ] No PostgreSQL UUID errors in logs

---

## 🧪 Testing Commands

```bash
# Test 1: Check symlink
readlink -f public_html/storage

# Test 2: Test audio file directly
curl -I https://education.medtrack.click/storage/audio/sentences/exercise-1-a-mae.mp3

# Test 3: Check Laravel logs for errors
tail -f ../storage/logs/laravel.log

# Test 4: Verify .htaccess is being read
cat public_html/.htaccess | grep "storage"
```

---

## 📝 Common Hostinger Notes

1. **Document Root:** Usually `public_html` or `domains/yoursite.com/public_html`
2. **Storage Path:** Usually `../storage` relative to `public_html`
3. **PHP Version:** Ensure PHP 8.1+ is enabled in Hostinger control panel
4. **LiteSpeed:** Hostinger uses LiteSpeed which is compatible with `.htaccess` Apache rules

---

## 🔄 Deployment Workflow

Every time you deploy:

1. **Upload files via FTP/Git**
2. **Run migrations** (if database changes)
3. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```
4. **Verify symlink exists:**
   ```bash
   ls -la public_html/storage
   ```
5. **Test storage URL in browser**

---

## 🎯 Expected Results

### ✅ Correct Behavior

**URL:** `https://education.medtrack.click/storage/audio/sentences/exercise-1-a-mae.mp3`

**Response:**
- HTTP 200 OK
- Content-Type: `audio/mpeg`
- Audio file plays in browser

### ❌ Incorrect Behavior (Fixed)

**URL:** `https://education.medtrack.click/api/exercises/exercise-1-a-mae.mp3`

**Old Response:**
- HTTP 500 Internal Server Error
- PostgreSQL UUID error

**New Response:**
- HTTP 404 Not Found (route doesn't match UUID constraint)
- No database query executed

---

## 📚 Related Files

- `routes/api.php` - API routes with UUID constraint
- `public/.htaccess` - URL rewriting rules
- `config/filesystems.php` - Storage disk configuration
- `app/Services/SimplePausedAudioService.php` - Audio generation

---

## 🆘 Support

If issues persist:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Apache/LiteSpeed error logs (Hostinger control panel)
3. Verify PHP version: `php -v`
4. Contact Hostinger support for symlink permissions
