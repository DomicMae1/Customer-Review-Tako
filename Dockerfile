# Gunakan image PHP dengan Apache built-in (lebih stabil untuk production)
FROM php:8.3-apache

# Install dependencies sistem dan ekstensi PHP yang dibutuhkan
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    ghostscript \
    nano \
    mc \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 600\n\
" > /usr/local/etc/php/conf.d/uploads.ini

# Aktifkan mod_rewrite untuk URL rewriting Laravel
RUN a2enmod rewrite ssl

# Ubah DocumentRoot Apache ke folder /public Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (untuk compile aset frontend)
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Set working directory
WORKDIR /var/www/html

# Copy semua file project ke dalam container
COPY . .

# Install dependency PHP (Composer)
RUN composer install --optimize-autoloader --no-dev

# Install dependency JS (NPM) & Build
RUN npm install && npm run build

# Buat Script Startup (Entrypoint) Langsung di dalam Dockerfile
RUN echo '#!/bin/bash\n\
\n\
# Pastikan folder permission storage internal benar\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache\n\
\n\
# Tangani Folder External (/mnt/Customer_Registration)\n\
# Kita ubah ownernya jadi www-data agar Apache bisa baca/tulis\n\
chown -R www-data:www-data /mnt/Customer_Registration\n\
chmod -R 775 /mnt/Customer_Registration\n\
\n\
if [ -f /tmp/hosts_external ]; then\n\
    echo "Processing /etc/hosts insertion..."\n\
    \n\
    # Langkah A: Bersihkan file dari host (buang localhost)\n\
    grep -v "127.0.0.1" /tmp/hosts_external | grep -v "::1" > /tmp/clean_hosts\n\
    \n\
    # Langkah B: Buat file baru gabungan (JANGAN pakai sed -i langsung ke /etc/hosts)\n\
    # Cari baris localhost, sisipkan isi clean_hosts di bawahnya, output ke file temp\n\
    sed "/127.0.0.1.*localhost/r /tmp/clean_hosts" /etc/hosts > /tmp/hosts.new\n\
    \n\
    # Langkah C: TIMPA isi /etc/hosts dengan isi file temp (Teknik 'cat' aman untuk Docker)\n\
    cat /tmp/hosts.new > /etc/hosts\n\
    \n\
    echo "✅ Success inserted external hosts directly below localhost"\n\
fi\n\
\n\
# Jalankan storage:link\n\
# Ini akan membaca config filesystems.php Anda dan membuat symlink\n\
# baik untuk public/storage maupun public/storage/external\n\
php artisan storage:link\n\
\n\
# Jalankan Apache\n\
exec apache2-foreground\n\
' > /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

# Gunakan Script Startup sebagai perintah utama
CMD ["/usr/local/bin/docker-entrypoint.sh"]