# NeoBiz Storage Enhanced v0.4.0 - File Structure

## 📁 Struktur File Plugin

```
neobiz-storage-enhanced/
├── neobiz-storage-enhanced.php    # File utama plugin
├── media-library-tabs.php         # Enhancement untuk media library
├── composer.json                   # Dependencies management
├── README.md                       # Dokumentasi lengkap
├── CHANGELOG.md                    # Riwayat perubahan
├── INSTALLATION.md                 # Panduan instalasi detail
└── templates/
    ├── admin.css                   # Styling modern untuk admin
    └── admin.js                    # JavaScript untuk AJAX & UI
```

## 🚀 Quick Start (5 Menit)

### 1. Upload ke WordPress
```bash
# Upload folder ke wp-content/plugins/
cp -r neobiz-storage-enhanced wp-content/plugins/
```

### 2. Install Dependencies
```bash
cd wp-content/plugins/neobiz-storage-enhanced
composer install
```

### 3. Activate Plugin
- Login ke WordPress Admin
- Go to **Plugins** > **Installed Plugins**
- Klik **Activate** pada "NeoBiz Storage Enhanced"

### 4. Konfigurasi Bucket Pertama
- Go to **NeoBiz Storage** > **Buckets**
- Isi form:
  - **Bucket Label**: "My Media Storage"
  - **Bucket Name**: nama bucket Anda
  - **Access Key**: access key dari S3/MinIO
  - **Secret Key**: secret key dari S3/MinIO
  - **Region**: region bucket (contoh: us-east-1)
  - **Endpoint**: URL endpoint
- Centang **"Set as default bucket"**
- Centang **"Active"**
- Klik **Add Bucket**

### 5. Test Upload
- Go to **Media** > **Add New**
- Upload test image
- Verify file muncul di bucket

## ✨ Fitur-Fitur Utama

### 🎨 Modern Dashboard
Dashboard yang informatif dengan:
- Statistics cards (buckets, files, sync status)
- Quick actions buttons
- Active buckets overview
- Real-time status indicators

### 🗄️ Multi-Bucket Management
- Kelola beberapa bucket sekaligus
- Per-bucket configuration
- Set default dan active/inactive bucket
- Auto-sync per bucket

### 🔄 Advanced Sync Manager
- **Manual Sync**: Batch upload dengan progress bar
- **Auto Discover**: Import files yang sudah ada di bucket
- Real-time logs dan statistics
- Force re-sync option

### 📊 Media Library Enhancement
- Bucket tabs untuk filter
- Bucket column di list view
- Single file sync button
- Bucket filter dropdown

### ⚙️ Flexible Settings
- Tab-based interface (General, Sync, Advanced)
- Modern toggle switches
- Auto/Manual sync mode
- Extensive customization options

## 📖 Dokumentasi Lengkap

### File Dokumentasi
1. **README.md** - Overview lengkap semua fitur
2. **INSTALLATION.md** - Panduan instalasi step-by-step
3. **CHANGELOG.md** - Riwayat perubahan dan roadmap

### Topik-Topik Penting

#### Cara Menambah Bucket Baru
Lihat: INSTALLATION.md > Initial Configuration > Step 1

#### Manual Sync Attachments
Lihat: README.md > Cara Menggunakan > Manual Sync

#### Auto Discover Files dari Bucket
Lihat: README.md > Cara Menggunakan > Auto Discover

#### Setup Provider (AWS, MinIO, DO Spaces)
Lihat: INSTALLATION.md > Provider-Specific Setup

#### Troubleshooting
Lihat: INSTALLATION.md > Troubleshooting

## 🔧 Konfigurasi Provider

### AWS S3
```
Provider: AWS S3
Endpoint: https://s3.amazonaws.com
Region: us-east-1 (atau region Anda)
Path Style: ❌ Unchecked
```

### MinIO (Local/Self-Hosted)
```
Provider: Custom/MinIO
Endpoint: http://localhost:9000
Region: us-east-1
Path Style: ✅ Checked
```

### DigitalOcean Spaces
```
Provider: DigitalOcean Spaces
Endpoint: https://nyc3.digitaloceanspaces.com
Region: nyc3
Path Style: ❌ Unchecked
```

### Wasabi
```
Provider: Wasabi
Endpoint: https://s3.wasabisys.com
Region: us-east-1
Path Style: ❌ Unchecked
```

## 🎯 Use Cases

### Scenario 1: Multiple Clients
- Buat bucket terpisah untuk setiap client
- Set client A bucket sebagai default
- Upload untuk client A otomatis ke bucket A
- Untuk client B, manual sync ke bucket B

### Scenario 2: Media Separation
- Bucket 1: Images (PNG, JPG)
- Bucket 2: Documents (PDF, DOC)
- Bucket 3: Videos (MP4, AVI)
- Filter di media library berdasarkan bucket

### Scenario 3: Migration
- Upload files ke bucket baru
- Keep old files di bucket lama
- Gradual migration dengan manual sync
- No downtime

### Scenario 4: Multi-Region
- Bucket 1: US-East (untuk US users)
- Bucket 2: EU-Central (untuk EU users)
- Bucket 3: AP-Southeast (untuk Asia users)
- Serve dari bucket terdekat

## ⚡ Performance Tips

1. **Enable CDN**
   - Set CDN Base URL di bucket settings
   - CloudFront untuk AWS
   - Spaces CDN untuk DigitalOcean
   - CloudFlare untuk lainnya

2. **Use Path Prefix**
   - Organize files dengan struktur folder
   - Contoh: `wp-uploads/2024/01/`

3. **Optimize Batch Size**
   - Default: 25 files per batch
   - High-performance: 50-100
   - Low-memory: 10-15

4. **Enable Cron Sync**
   - Background processing
   - No user-facing delays
   - Automatic retry on failure

5. **Delete Local Files**
   - Save server storage
   - Faster backups
   - ⚠️ Make sure bucket is reliable

## 🔐 Security Checklist

- [ ] Use IAM user dengan minimum permissions
- [ ] Don't use root AWS credentials
- [ ] Store credentials di wp-config.php (recommended)
- [ ] Enable HTTPS untuk WordPress dan endpoints
- [ ] Regular backup database dan bucket
- [ ] Monitor access logs
- [ ] Use private bucket jika sensitive data
- [ ] Enable SSL verification (disable only for dev)

## 🐛 Common Issues & Solutions

### Issue 1: SDK Not Found
```bash
# Solution
cd wp-content/plugins/neobiz-storage-enhanced
composer install
```

### Issue 2: Upload Fails
- Check credentials
- Verify bucket permissions
- Check endpoint URL format
- Enable debug mode

### Issue 3: Sync Hangs
- Increase PHP max_execution_time
- Reduce batch size
- Check server resources

### Issue 4: URL Not Rewriting
- Verify plugin enabled
- Check file has `_nbs_offloaded` meta
- Verify CDN Base URL

## 📞 Support & Resources

### Documentation
- **README.md** - Panduan lengkap
- **INSTALLATION.md** - Setup guide
- **CHANGELOG.md** - Update history

### Online Resources
- GitHub Repository: [Link to repo]
- Issues & Bugs: [GitHub Issues]
- Community Forum: [WordPress.org]
- Email Support: support@neobiz.id

### WP-CLI Commands (Coming in v0.5)
```bash
# List buckets
wp neobiz buckets

# Sync to bucket
wp neobiz sync --bucket=my-bucket

# Discover bucket files
wp neobiz discover --bucket=my-bucket

# Clear cache
wp neobiz clear-cache
```

## 🎉 What's New in v0.4.0

✅ Complete UI redesign dengan modern dashboard
✅ Multi-bucket management system
✅ Advanced sync manager dengan progress tracking
✅ Auto discover untuk import existing files
✅ Media library bucket tabs dan filters
✅ Enhanced settings dengan tab interface
✅ Real-time AJAX operations
✅ Comprehensive logging system
✅ Better error handling dan notifications

## 📝 Changelog Highlights

### Added
- Modern dashboard dengan statistics
- Multi-bucket support
- Manual sync dengan progress bar
- Auto discover functionality
- Media library enhancements
- Tab-based settings

### Changed
- Complete UI redesign
- Better code organization
- Improved performance
- Enhanced security

### Improved
- Error handling
- User feedback
- Documentation
- Database optimization

## 🗺️ Roadmap

### v0.5.0 (Coming Soon)
- Bucket tabs di media uploader modal
- Bulk operations
- CDN purge integration
- Migration tools

### v0.6.0
- Multi-site support
- Advanced analytics
- Backup & restore
- Cost calculator

### v1.0.0
- Image optimization
- Video transcoding
- Enterprise features
- SLA monitoring

---

## 🙏 Credits

Developed with ❤️ by **NeoBiz Team**

Based on community feedback and real-world usage.

---

**Selamat menggunakan NeoBiz Storage Enhanced! ☁️**

Jika ada pertanyaan, jangan ragu untuk menghubungi support.
