FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Copy project files to Apache folder
COPY . /var/www/html/

# Set correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expose port and handle dynamic PORT from Railway
EXPOSE 80

# Create Apache config for dynamic port
RUN echo 'Listen 0.0.0.0:${PORT}' > /etc/apache2/ports.conf

# Use the PORT environment variable if provided by Railway, default to 80
CMD ["/bin/bash", "-c", "sed -i \"s/Listen 80/Listen 0.0.0.0:${PORT:-80}/g\" /etc/apache2/ports.conf && apache2-foreground"]