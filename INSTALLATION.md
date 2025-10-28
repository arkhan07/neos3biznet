# Installation & Quick Start Guide

Panduan lengkap instalasi dan konfigurasi NeoBiz Storage Enhanced.

## 📋 System Requirements

### Minimum Requirements
- **PHP**: 7.4 atau lebih tinggi
- **WordPress**: 5.0 atau lebih tinggi
- **MySQL**: 5.6 atau lebih tinggi
- **Composer**: Latest version
- **Memory**: 128MB PHP memory limit (256MB recommended)
- **Storage**: S3-compatible storage (AWS S3, MinIO, DigitalOcean Spaces, Wasabi, dll)

### Recommended Requirements
- **PHP**: 8.0+
- **WordPress**: 6.0+
- **Memory**: 256MB+
- **SSL**: HTTPS enabled
- **Cron**: WP-Cron atau system cron enabled

## 🚀 Installation Methods

### Method 1: Manual Installation (Recommended)

1. **Download Plugin**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/neobiz/storage-enhanced.git neobiz-storage
   # atau download ZIP dan extract
   ```

2. **Install Dependencies**
   ```bash
   cd neobiz-storage
   composer install --no-dev --optimize-autoloader
   ```

3. **Verify Installation**
   ```bash
   ls -la vendor/aws/  # Should show aws-sdk-php directory
   ```

4. **Activate Plugin**
   - Login ke WordPress Admin
   - Go to Plugins > Installed Plugins
   - Find "NeoBiz Storage Enhanced"
   - Click "Activate"

5. **Verify Activation**
   - Check for "NeoBiz Storage" in admin menu
   - Go to NeoBiz Storage > Dashboard
   - Verify no errors displayed

### Method 2: Upload via WordPress Admin

1. **Prepare Plugin**
   ```bash
   # On your local machine
   composer install --no-dev --optimize-autoloader
   zip -r neobiz-storage.zip . -x "*.git*" "node_modules/*" "tests/*"
   ```

2. **Upload**
   - Go to WordPress Admin > Plugins > Add New
   - Click "Upload Plugin"
   - Choose `neobiz-storage.zip`
   - Click "Install Now"
   - Click "Activate Plugin"

### Method 3: Using Composer (WordPress Composer Project)

Add to your `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/neobiz/storage-enhanced"
        }
    ],
    "require": {
        "neobiz/storage-enhanced": "^0.4"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
        }
    }
}
```

Run:
```bash
composer install
```

## ⚙️ Initial Configuration

### Step 1: Configure First Bucket

1. **Navigate to Buckets**
   - Go to NeoBiz Storage > Buckets

2. **Fill Bucket Form**
   
   #### Basic Information
   - **Bucket Label**: `Production Media` (friendly name)
   - **Bucket Name**: `my-wordpress-bucket` (actual S3 bucket name)
   - **Provider**: Select from dropdown or choose "Custom/MinIO"

   #### Credentials
   - **Access Key**: Your S3 Access Key ID
   - **Secret Key**: Your S3 Secret Access Key

   #### Connection Settings
   - **Region**: `us-east-1` (or your bucket region)
   - **Endpoint URL**: 
     - AWS S3: `https://s3.amazonaws.com`
     - MinIO: `http://localhost:9000` or your MinIO URL
     - DO Spaces: `https://nyc3.digitaloceanspaces.com`
     - Wasabi: `https://s3.wasabisys.com`

   #### Optional Settings
   - **Path Prefix**: `wp-uploads/` (organize files in subfolder)
   - **CDN Base URL**: `https://cdn.yourdomain.com` (if using CDN)

3. **Configure Options**
   - ✅ Use path-style addressing (required for MinIO)
   - ✅ Set as default bucket
   - ✅ Active (show in media library)
   - ⬜ Auto sync uploads to this bucket (optional)

4. **Save Bucket**
   - Click "Add Bucket"
   - Verify success message appears

### Step 2: Test Connection

1. **Test Upload**
   - Go to Media > Add New
   - Upload a test image
   - Check if upload succeeds

2. **Verify in Bucket**
   - Login to your S3/MinIO console
   - Navigate to your bucket
   - Verify file exists in bucket

3. **Check URL**
   - Go to Media > Library
   - Click on uploaded image
   - Verify URL points to bucket/CDN

### Step 3: Configure General Settings

1. **Navigate to Settings**
   - Go to NeoBiz Storage > Settings

2. **General Tab**
   - ✅ Enable Plugin
   - ✅ Multi-Bucket Mode (for multiple buckets)
   - ✅ Auto Discover (to import existing files)
   - ⬜ Delete Local Files (careful! saves server space but risky)

3. **Sync Options Tab**
   - **Sync Mode**: Choose "Auto" or "Manual"
     - Auto: Upload immediately on media upload
     - Manual: Use Sync Manager to batch upload
   - **Cron Sync**: Enable for background sync
   - **Batch Size**: 25 (adjust based on server resources)
   - **Exclude MIME Types**: Leave empty or add: `video/mp4,video/mpeg`

4. **Advanced Tab**
   - **Private Bucket**: Enable if bucket is private (uses presigned URLs)
   - **Presigned URL TTL**: 3600 seconds (1 hour)
   - **Cache Control**: `public, max-age=31536000, immutable`
   - **Storage Class**: Leave default or select appropriate class
   - **Disable SSL Verify**: Only for development with self-signed certs

5. **Save Settings**

## 🔧 Provider-Specific Setup

### AWS S3

1. **Create IAM User**
   ```bash
   # Minimum IAM Policy
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "s3:PutObject",
           "s3:GetObject",
           "s3:DeleteObject",
           "s3:ListBucket",
           "s3:GetBucketLocation"
         ],
         "Resource": [
           "arn:aws:s3:::your-bucket-name",
           "arn:aws:s3:::your-bucket-name/*"
         ]
       }
     ]
   }
   ```

2. **Bucket Settings**
   - Endpoint: `https://s3.amazonaws.com` or region-specific
   - Region: Your bucket region (e.g., `us-east-1`)
   - Path Style: Unchecked

3. **CORS Configuration** (if using direct access)
   ```json
   [
     {
       "AllowedOrigins": ["https://yourdomain.com"],
       "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
       "AllowedHeaders": ["*"],
       "ExposeHeaders": ["ETag"],
       "MaxAgeSeconds": 3000
     }
   ]
   ```

### MinIO

1. **Install MinIO** (if not already)
   ```bash
   docker run -p 9000:9000 -p 9001:9001 \
     -e "MINIO_ROOT_USER=admin" \
     -e "MINIO_ROOT_PASSWORD=adminpassword" \
     minio/minio server /data --console-address ":9001"
   ```

2. **Create Bucket**
   - Access MinIO Console: `http://localhost:9001`
   - Login with root credentials
   - Create bucket: `wordpress-media`
   - Set bucket policy to public (or use presigned URLs)

3. **Plugin Configuration**
   - Provider: Custom/MinIO
   - Endpoint: `http://localhost:9000`
   - Region: `us-east-1` (doesn't matter for MinIO)
   - Path Style: ✅ Checked (required)

### DigitalOcean Spaces

1. **Create Space**
   - Go to DigitalOcean Control Panel
   - Create new Space
   - Choose region (e.g., NYC3)

2. **Generate API Keys**
   - Go to API > Spaces Keys
   - Generate new key pair
   - Save Access Key and Secret Key

3. **Plugin Configuration**
   - Provider: DigitalOcean Spaces
   - Endpoint: `https://nyc3.digitaloceanspaces.com` (replace region)
   - Region: `nyc3` (or your region)
   - Path Style: Unchecked

4. **CDN Setup** (optional)
   - Enable Spaces CDN in DO control panel
   - Copy CDN endpoint
   - Add to plugin CDN Base URL field

## 📤 First Sync

### Option A: Upload New Files
1. Go to Media > Add New
2. Upload some test images
3. Verify they appear in bucket

### Option B: Sync Existing Files
1. Go to NeoBiz Storage > Sync Manager
2. Select bucket (or "All Active Buckets")
3. Click "Start Sync"
4. Monitor progress bar
5. Wait for completion

### Option C: Auto Discover Existing Bucket Files
1. If you already have files in bucket
2. Go to NeoBiz Storage > Sync Manager
3. Go to "Auto Discover" section
4. Select bucket
5. Check "Skip files already in media library"
6. Click "Start Discovery"
7. Files will be imported to media library

## ✅ Verification Checklist

After setup, verify:

- [ ] Plugin activated without errors
- [ ] At least one bucket configured
- [ ] Test upload succeeds
- [ ] File appears in bucket
- [ ] URL rewriting works
- [ ] Media library shows bucket column
- [ ] Dashboard shows statistics
- [ ] Sync manager accessible
- [ ] Settings saved properly

## 🐛 Troubleshooting

### Issue: SDK Not Found

**Error**: "AWS SDK not found. Run composer install"

**Solution**:
```bash
cd wp-content/plugins/neobiz-storage
composer install --no-dev
```

### Issue: Upload Fails

**Check**:
1. Credentials are correct
2. Bucket permissions (s3:PutObject)
3. Endpoint URL is correct
4. Region matches bucket region
5. Check debug.log: `tail -f wp-content/debug.log`

**Enable Debug Mode**:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Issue: URL Not Rewriting

**Check**:
1. Plugin is enabled
2. File has `_nbs_offloaded` meta
3. CDN Base URL is correct
4. Private bucket setting matches actual bucket ACL

### Issue: Sync Hangs

**Solutions**:
1. Increase PHP max_execution_time:
   ```php
   // wp-config.php
   set_time_limit(300);
   ```

2. Reduce batch size in Settings

3. Check server resources (memory, CPU)

### Issue: Permission Denied

**AWS S3**: Check IAM policy includes required actions

**MinIO**: Set bucket policy:
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {"AWS": ["*"]},
      "Action": ["s3:GetObject"],
      "Resource": ["arn:aws:s3:::wordpress-media/*"]
    }
  ]
}
```

## 🔐 Security Best Practices

1. **Use IAM Users with Minimum Permissions**
   - Don't use root AWS credentials
   - Create dedicated IAM user for WordPress

2. **Store Credentials Securely**
   ```php
   // wp-config.php (recommended)
   define('NBS_ACCESS_KEY', 'your-access-key');
   define('NBS_SECRET_KEY', 'your-secret-key');
   ```

3. **Enable HTTPS**
   - Use SSL for WordPress site
   - Use HTTPS endpoints

4. **Regular Backups**
   - Backup WordPress database
   - Backup bucket contents
   - Test restore procedures

5. **Monitor Access**
   - Enable CloudTrail (AWS)
   - Review access logs
   - Monitor for unusual activity

## 📚 Next Steps

After successful installation:

1. **Read Documentation**: [README-ENHANCED.md](README-ENHANCED.md)
2. **Review Settings**: Fine-tune for your needs
3. **Add More Buckets**: If using multi-bucket setup
4. **Configure Cron**: For automated sync
5. **Setup CDN**: For better performance
6. **Monitor Logs**: Check sync activity

## 🆘 Getting Help

- Documentation: [GitHub Wiki]
- Issues: [GitHub Issues]
- Forum: [WordPress.org Plugin Support]
- Email: support@neobiz.id

---

**Happy Uploading to the Cloud! ☁️**
