# ⚡ Quick Reference Card

## 📦 Plugin Files Overview
```
Total Files: 10
- PHP Files: 2 (main + media library tabs)
- CSS Files: 1 (modern admin styling)
- JS Files: 1 (AJAX handlers)
- Documentation: 5 (README, INSTALLATION, CHANGELOG, etc)
- Config: 1 (composer.json)
```

## 🚀 Installation (3 Steps)

```bash
# 1. Extract to plugins folder
cd wp-content/plugins/
unzip neobiz-storage-enhanced.zip

# 2. Install dependencies
cd neobiz-storage-enhanced
composer install

# 3. Activate via WordPress Admin
# Plugins > Activate "NeoBiz Storage Enhanced"
```

## ⚙️ First Time Setup (5 Minutes)

### Step 1: Add Bucket
```
NeoBiz Storage > Buckets > Add New Bucket
- Bucket Label: "Production Media"
- Bucket Name: your-bucket-name
- Access Key: [your-key]
- Secret Key: [your-secret]
- Region: us-east-1
- Endpoint: https://s3.amazonaws.com
✓ Set as default
✓ Active
```

### Step 2: Configure Settings
```
NeoBiz Storage > Settings
✓ Enable Plugin
✓ Multi-Bucket Mode
✓ Auto Discover
Sync Mode: Auto (or Manual)
```

### Step 3: Test
```
Media > Add New > Upload test image
Verify: Check if file appears in bucket
```

## 🎯 Common Tasks

### Upload Files
```
Media > Add New > Upload
(Auto-uploaded to default bucket)
```

### Manual Sync All Files
```
NeoBiz Storage > Sync Manager
Select: Bucket or "All Active Buckets"
Click: Start Sync
Wait: Progress bar completes
```

### Import Existing Bucket Files
```
NeoBiz Storage > Sync Manager
Go to: Auto Discover section
Select: Bucket to scan
✓ Skip files already in library
Click: Start Discovery
```

### Filter by Bucket
```
Media > Library
Use: Bucket filter dropdown
or
Click: Bucket tabs
```

### Sync Single File
```
Media > Library
Hover over file
Click: "Sync to Cloud" or "Re-sync"
```

## 🔧 Provider Quick Setup

### AWS S3
```
Endpoint: https://s3.amazonaws.com
Region: [your-region]
Path Style: OFF
```

### MinIO
```
Endpoint: http://localhost:9000
Region: us-east-1
Path Style: ON ✓
```

### DigitalOcean Spaces
```
Endpoint: https://[region].digitaloceanspaces.com
Region: [nyc3|sgp1|etc]
Path Style: OFF
```

### Wasabi
```
Endpoint: https://s3.wasabisys.com
Region: us-east-1
Path Style: OFF
```

## 📊 Admin Pages

### Dashboard
```
NeoBiz Storage > Dashboard
- View statistics
- Quick actions
- Active buckets overview
- Sync individual buckets
```

### Buckets Manager
```
NeoBiz Storage > Buckets
- Add new bucket
- Edit existing
- Delete bucket
- View all configurations
```

### Sync Manager
```
NeoBiz Storage > Sync Manager
- Manual sync (batch upload)
- Auto discover (import files)
- View progress
- Monitor logs
```

### Settings
```
NeoBiz Storage > Settings
- General settings
- Sync options
- Advanced configuration
```

## 🐛 Troubleshooting Quick Fixes

### SDK Not Found
```bash
cd wp-content/plugins/neobiz-storage-enhanced
composer install
```

### Upload Fails
```
1. Check credentials
2. Verify bucket permissions
3. Test endpoint URL
4. Enable WP_DEBUG
```

### Sync Hangs
```
Settings > Sync Options
Reduce Batch Size to 10-15
or
Increase PHP max_execution_time
```

### Files Not Showing
```
1. Check bucket is Active
2. Run Auto Discover
3. Verify metadata exists
```

## 🔐 Security Checklist

```
✓ Use IAM user (not root)
✓ Minimum permissions only
✓ Store credentials securely
✓ Enable HTTPS
✓ Regular backups
✓ Monitor access logs
```

## 📁 Important Files

### Documentation
- `README.md` - Complete guide
- `INSTALLATION.md` - Setup instructions
- `CHANGELOG.md` - Version history
- `FILE-STRUCTURE.md` - File overview
- `SUMMARY.md` - What's included

### Code
- `neobiz-storage-enhanced.php` - Main plugin
- `media-library-tabs.php` - Media library features
- `templates/admin.css` - Styling
- `templates/admin.js` - JavaScript

### Config
- `composer.json` - Dependencies
- `.gitignore` - Git ignore rules
- `LICENSE` - GPL-2.0

## 🎨 UI Features

### Dashboard
- Statistics cards with gradients
- Quick action buttons
- Bucket overview cards
- Real-time status

### Forms
- Modern input fields
- Toggle switches
- Dropdown selectors
- Help tooltips

### Progress
- Animated progress bar
- Real-time counters
- Scrollable logs
- Color-coded status

### Tables
- Sortable columns
- Action buttons
- Status badges
- Responsive layout

## ⚡ Performance Tips

```
1. Enable CDN for faster delivery
2. Use path prefix for organization
3. Optimize batch size (25-50)
4. Enable cron sync
5. Consider deleting local files
```

## 📞 Getting Help

### Documentation Order
1. Read SUMMARY.md (overview)
2. Read FILE-STRUCTURE.md (quick ref)
3. Read INSTALLATION.md (detailed setup)
4. Read README.md (complete guide)
5. Read CHANGELOG.md (what's new)

### Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
// Check: wp-content/debug.log
```

### Support
- GitHub Issues
- WordPress.org Forum
- Email: support@neobiz.id

## 🎯 Version Info

```
Version: 0.4.0
PHP: 7.4+
WordPress: 5.0+
Dependencies: aws/aws-sdk-php ^3.307
License: GPL-2.0-or-later
```

## 🌟 Key Features

```
✅ Multi-bucket support
✅ Modern dashboard UI
✅ Real-time sync progress
✅ Auto discover files
✅ Media library tabs
✅ Bucket filtering
✅ Single file sync
✅ CDN integration
✅ Private bucket support
✅ Comprehensive logging
```

## 📝 Quick Commands

### Check Plugin Status
```
WordPress Admin > Plugins
Look for: "NeoBiz Storage Enhanced"
Status should be: "Active"
```

### View Logs
```
NeoBiz Storage > Sync Manager
Check: Recent Sync Activity
or
Check: wp-content/debug.log
```

### Reset Settings
```
WP Admin > Settings > NeoBiz Storage
Reset to defaults or reconfigure
```

## 🎉 Success Indicators

```
✓ No errors on activation
✓ Menu appears in admin
✓ Test upload succeeds
✓ File appears in bucket
✓ URL rewriting works
✓ Stats show correctly
```

---

## 💡 Pro Tips

1. **Start Small**: Test dengan 1 bucket dulu
2. **Use CDN**: Setup CDN untuk better performance
3. **Monitor First**: Watch sync logs initially
4. **Backup Always**: Database backup sebelum production
5. **Gradual Migration**: Migrate bertahap, bukan sekaligus

---

**Quick Reference by NeoBiz Team**  
**Need Help?** Check INSTALLATION.md untuk detailed guide!  
**Happy Cloud Uploading! ☁️**
