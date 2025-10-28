# NeoBiz Storage Enhanced v0.4.0

Plugin WordPress untuk offload media ke S3-compatible storage dengan fitur multi-bucket, sinkronisasi modern, dan UI yang lebih cantik.

## ✨ Fitur Baru v0.4.0

### 🎨 **Modern UI Dashboard**
- Dashboard yang lebih modern dan informatif
- Statistics cards dengan visualisasi yang menarik
- Quick actions untuk akses cepat
- Overview bucket yang aktif dengan status realtime

### 🗄️ **Multi-Bucket Management**
- Kelola beberapa bucket sekaligus
- Setiap bucket punya konfigurasi sendiri (credentials, region, endpoint, CDN)
- Set bucket default dan aktif/nonaktif bucket
- Auto-sync per bucket
- Tab bucket di media library (coming soon)

### 🔄 **Advanced Sync Manager**
- **Manual Sync**: Sync semua attachment dengan progress bar realtime
- **Auto Discover**: Scan dan import files yang sudah ada di bucket
- Progress tracking dengan statistik detail
- Logs realtime untuk monitoring
- Force re-sync untuk files yang sudah di-upload
- Batch processing yang efisien

### 🔍 **Auto Discovery**
- Otomatis scan bucket dan detect files
- Import files dari bucket ke WordPress media library
- Skip files yang sudah ada (opsional)
- Background processing untuk bucket besar

### 📊 **Enhanced Dashboard**
- Total buckets dan active buckets counter
- Total attachments vs offloaded counter  
- Sync mode indicator (Manual/Auto)
- Auto discover status
- Quick sync per bucket dari dashboard

### ⚙️ **Better Settings**
- Tab-based settings untuk navigasi lebih mudah
- Toggle switches yang modern
- Form validation dan user feedback
- Preset untuk provider populer (AWS, DigitalOcean, Wasabi, MinIO)

## 📦 Instalasi

### Requirements
- PHP 7.4 atau lebih tinggi
- WordPress 5.0 atau lebih tinggi
- Composer (untuk install dependencies)

### Langkah Instalasi

1. **Upload plugin ke WordPress**
   ```bash
   cd wp-content/plugins
   # Upload folder plugin atau clone dari git
   ```

2. **Install dependencies**
   ```bash
   cd neobiz-storage
   composer install
   ```

3. **Activate plugin**
   - Masuk ke WordPress Admin > Plugins
   - Activate "NeoBiz Storage Enhanced"

4. **Konfigurasi bucket pertama**
   - Masuk ke NeoBiz Storage > Buckets
   - Klik "Add New Bucket"
   - Isi form dengan credentials S3/MinIO Anda
   - Set sebagai default bucket
   - Save

## 🚀 Cara Menggunakan

### Menambah Bucket Baru

1. Buka **NeoBiz Storage > Buckets**
2. Isi form di sebelah kiri:
   - **Bucket Label**: Nama friendly untuk bucket (contoh: "Production Images")
   - **Bucket Name**: Nama bucket di S3 (contoh: "my-bucket")
   - **Provider**: Pilih provider atau custom
   - **Access Key & Secret Key**: Credentials dari S3/MinIO
   - **Region**: Region bucket (contoh: us-east-1)
   - **Endpoint URL**: URL endpoint (contoh: https://s3.amazonaws.com)
   - **Path Prefix**: (Opsional) Folder di dalam bucket
   - **CDN Base URL**: (Opsional) URL CDN untuk serving files
3. Centang opsi yang diinginkan:
   - **Use path-style**: Untuk MinIO atau S3-compatible lainnya
   - **Set as default**: Bucket default untuk upload baru
   - **Active**: Tampilkan di media library
   - **Auto sync**: Otomatis sync upload ke bucket ini
4. Klik **Add Bucket**

### Manual Sync Attachments

1. Buka **NeoBiz Storage > Sync Manager**
2. Di bagian "Manual Sync":
   - Pilih bucket target (atau "All Active Buckets")
   - Centang "Force re-sync" jika ingin re-upload files yang sudah ada
   - Centang "Delete local files" jika ingin hapus file lokal setelah sync
3. Klik **Start Sync**
4. Monitor progress bar dan logs
5. Tunggu sampai selesai

### Auto Discover Files dari Bucket

1. Buka **NeoBiz Storage > Sync Manager**
2. Di bagian "Auto Discover":
   - Pilih bucket yang ingin di-scan
   - Centang "Skip files already in media library" (recommended)
3. Klik **Start Discovery**
4. Plugin akan scan bucket dan import files ke media library
5. Files yang di-import akan muncul di Media Library dengan metadata lengkap

### Sync Individual Bucket

1. Buka **NeoBiz Storage > Dashboard**
2. Di bagian "Active Buckets", klik tombol **Sync Now** pada bucket yang diinginkan
3. Files akan otomatis di-sync ke bucket tersebut

### Settings Global

1. Buka **NeoBiz Storage > Settings**
2. Tab **General**:
   - Enable/disable plugin
   - Enable multi-bucket mode
   - Enable auto discover
   - Delete local files after upload
3. Tab **Sync Options**:
   - Set sync mode (Manual/Auto)
   - Enable cron sync
   - Set batch size
   - Exclude MIME types tertentu
4. Tab **Advanced**:
   - Private bucket dengan presigned URLs
   - Cache control headers
   - Storage class
   - SSL verification

## 🔧 Fitur Detail

### Multi-Bucket Support

Plugin mendukung multiple buckets dengan konfigurasi berbeda:
- Setiap bucket punya credentials sendiri
- Bisa mix AWS S3, MinIO, DigitalOcean Spaces, dll
- Upload attachment otomatis ke default bucket
- Manual sync bisa pilih bucket target
- Tab per-bucket di media library (v0.5.0)

### Sync Modes

**Manual Mode**:
- Upload tidak otomatis di-sync
- Gunakan Sync Manager untuk batch processing
- Kontrol penuh kapan sync dilakukan

**Auto Mode**:
- Upload otomatis di-sync ke bucket
- Realtime processing
- Best untuk production

### Auto Discover

Scan bucket dan import files yang sudah ada:
- Support untuk files yang di-upload manual ke bucket
- Detect MIME type otomatis
- Create attachment records di WordPress
- Link files ke bucket tanpa download ulang

### URL Rewriting

Plugin otomatis rewrite URL media:
- Gunakan CDN URL jika dikonfigurasi
- Fallback ke bucket URL
- Support presigned URLs untuk private bucket
- No database migration needed

## 📝 Database Schema

Plugin membuat 2 tabel tambahan:

### wp_nbs_buckets
Menyimpan konfigurasi semua bucket:
- Credentials (access_key, secret_key)
- Configuration (region, endpoint, path_prefix)
- Settings (is_default, is_active, auto_sync)
- Metadata (last_sync, created_at)

### wp_nbs_sync_log
Log semua aktivitas sync:
- attachment_id dan bucket_id
- Action (upload, manual_sync, discover)
- Status (success, failed)
- Timestamp

## 🎯 Roadmap

### v0.5.0 (Coming Soon)
- [ ] Bucket tabs di Media Library
- [ ] Filter attachments by bucket
- [ ] Bulk actions di media library
- [ ] CDN purge integration

### v0.6.0
- [ ] Migration tools (bucket to bucket)
- [ ] Advanced analytics
- [ ] Backup & restore
- [ ] Multi-site support

## 🐛 Troubleshooting

### SDK Not Found
```
Run: composer install
```

### Upload Failed
- Cek credentials bucket
- Verifikasi bucket permissions (PutObject)
- Cek endpoint URL format
- Disable SSL verify jika self-signed certificate

### Files Not Showing
- Enable bucket (is_active = 1)
- Run Auto Discover untuk import existing files
- Cek metadata: _nbs_offloaded, _nbs_bucket, _nbs_key

### Sync Stuck
- Increase PHP max_execution_time
- Reduce batch_size di settings
- Cek error logs: wp-content/debug.log

## 🔐 Security

- Credentials disimpan encrypted (recommended: use wp-config.php constants)
- Support private buckets dengan presigned URLs
- ACL management per upload
- No credentials di frontend

## 💡 Tips & Best Practices

1. **Gunakan CDN**: Set CDN Base URL untuk performa maksimal
2. **Path Prefix**: Organize files dengan prefix (contoh: wp-uploads/)
3. **Auto Sync**: Enable per-bucket untuk automatic processing
4. **Cron Sync**: Background processing untuk large media library
5. **Exclude MIME**: Skip video files jika ukuran besar
6. **Backup**: Always backup database sebelum migration

## 📄 License

GPL-2.0-or-later

## 👥 Credits

Developed by NeoBiz Team

## 📞 Support

- GitHub Issues: [Create Issue]
- Documentation: [Wiki]
- Email: support@neobiz.id

---

**Enjoy uploading to the cloud! ☁️**
# neos3biznet
